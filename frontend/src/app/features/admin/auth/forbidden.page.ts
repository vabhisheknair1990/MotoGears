import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { EmptyStateComponent } from '../../../shared/components/empty-state.component';

@Component({
  selector: 'adm-forbidden-page',
  imports: [RouterLink, EmptyStateComponent],
  template: `
    <div class="card">
      <app-empty-state icon="lock" title="You don't have access to this section" message="Your role doesn't include the permission this page needs. Ask a super admin to update your role.">
        <a routerLink="/admin" class="btn btn-primary">Back to admin home</a>
      </app-empty-state>
    </div>
  `,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForbiddenPage {}
