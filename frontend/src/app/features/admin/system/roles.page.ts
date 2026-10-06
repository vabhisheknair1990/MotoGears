import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { SeoService } from '../../../core/services/seo.service';
import { ToastService } from '../../../core/services/toast.service';
import { errorMessage } from '../../../core/utils/http-errors';
import { IconComponent } from '../../../shared/components/icon.component';
import { AdminApiService } from '../data/admin-api.service';
import { PermissionRow, RoleRow } from '../data/admin.models';
import { PageHeaderComponent } from '../shared/page-header.component';

interface RolesPayload { roles: RoleRow[]; permissions: Record<string, PermissionRow[]> }

@Component({
  selector: 'adm-roles-page',
  imports: [IconComponent, PageHeaderComponent],
  template: `
    <adm-page-header title="Roles & permissions" subtitle="Tick what each staff role may do. Laravel enforces these on every admin API call; the Super Admin role always has full access." />
    @if (error()) { <div class="alert alert-danger"><app-icon name="alert" [size]="18" /> {{ error() }}</div> }
    @if (data(); as d) {
      <section class="card">
        <div class="table-wrap flat matrix">
          <table class="table">
            <thead>
              <tr>
                <th scope="col" class="perm-col">Permission</th>
                @for (r of staffRoles(); track r.id) {
                  <th scope="col" class="center role"><span>{{ r.label }}</span><small>{{ r.users_count }} user{{ r.users_count === 1 ? '' : 's' }}</small></th>
                }
              </tr>
            </thead>
            <tbody>
              @for (g of groups(); track g.name) {
                <tr class="grp"><th [attr.colspan]="staffRoles().length + 1" scope="rowgroup">{{ g.name }}</th></tr>
                @for (p of g.perms; track p.id) {
                  <tr>
                    <th scope="row" class="perm-col"><span class="fw-600">{{ p.label }}</span><div class="text-xs text-muted mono">{{ p.name }}</div></th>
                    @for (r of staffRoles(); track r.id) {
                      <td class="center">
                        <input type="checkbox" [checked]="has(r, p.name)" [disabled]="r.name === 'super_admin' || saving() === r.id" (change)="toggle(r, p.name, $any($event.target).checked)" [attr.aria-label]="r.label + ': ' + p.label" />
                      </td>
                    }
                  </tr>
                }
              }
            </tbody>
            <tfoot>
              <tr>
                <td></td>
                @for (r of staffRoles(); track r.id) {
                  <td class="center">
                    @if (r.name !== 'super_admin') {
                      <button type="button" class="btn btn-sm" [class.btn-primary]="dirty().has(r.id)" [disabled]="!dirty().has(r.id) || saving() === r.id" (click)="save(r)">@if (saving() === r.id) { <span class="spinner"></span> } Save</button>
                    } @else { <span class="text-xs text-muted">Locked</span> }
                  </td>
                }
              </tr>
            </tfoot>
          </table>
        </div>
      </section>
      <p class="text-sm text-muted">Tip: assign roles to people on the <strong>Staff</strong> page. Customers have no admin permissions.</p>
    } @else if (!error()) {
      <div class="card"><div class="skeleton" style="height:360px;margin:16px"></div></div>
    }
  `,
  styles: `
    .flat { border: 0; border-radius: 0; }
    .matrix { overflow-x: auto; }
    .center { text-align: center; }
    .role span { display: block; white-space: nowrap; }
    .role small { font-weight: 400; color: var(--muted); font-size: 11px; }
    .perm-col { text-align: left; min-width: 220px; font-weight: 400; }
    .grp th { background: var(--line-2); font-size: 11.5px; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); text-align: left; }
    input[type='checkbox'] { width: 17px; height: 17px; accent-color: var(--brand); cursor: pointer; }
    tfoot td { border-top: 1px solid var(--line); }
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class RolesPage {
  private readonly api = inject(AdminApiService);
  private readonly toast = inject(ToastService);
  protected readonly data = signal<RolesPayload | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly saving = signal<number | null>(null);
  protected readonly dirty = signal(new Set<number>());
  private readonly edits = new Map<number, Set<string>>();

  protected readonly staffRoles = computed(() => (this.data()?.roles ?? []).filter((r) => r.is_staff));
  protected readonly groups = computed(() => Object.entries(this.data()?.permissions ?? {}).map(([name, perms]) => ({ name, perms })));

  constructor() {
    inject(SeoService).set({ title: 'Roles & permissions' });
    this.load();
  }

  private load(): void {
    this.api.get<RolesPayload>('roles').subscribe({
      next: (d) => {
        this.edits.clear();
        d.roles.forEach((r) => this.edits.set(r.id, new Set(r.permissions)));
        this.dirty.set(new Set());
        this.data.set(d);
      },
      error: (err) => this.error.set(errorMessage(err, 'Could not load roles.')),
    });
  }

  protected has(r: RoleRow, perm: string): boolean {
    return this.edits.get(r.id)?.has(perm) ?? false;
  }

  protected toggle(r: RoleRow, perm: string, on: boolean): void {
    const set = this.edits.get(r.id) ?? new Set<string>();
    if (on) set.add(perm);
    else set.delete(perm);
    this.edits.set(r.id, set);
    this.dirty.update((d) => new Set(d).add(r.id));
  }

  protected save(r: RoleRow): void {
    this.saving.set(r.id);
    this.api.put<RolesPayload>(`roles/${r.id}`, { permissions: [...(this.edits.get(r.id) ?? [])] }).subscribe({
      next: (res) => {
        this.saving.set(null);
        this.toast.success(`${r.label} permissions saved`);
        this.dirty.update((d) => {
          const n = new Set(d);
          n.delete(r.id);
          return n;
        });
        if (res.data?.roles) {
          this.data.set(res.data);
          res.data.roles.forEach((x) => { if (!this.dirty().has(x.id)) this.edits.set(x.id, new Set(x.permissions)); });
        }
      },
      error: (err) => {
        this.saving.set(null);
        this.toast.error(errorMessage(err));
      },
    });
  }
}
