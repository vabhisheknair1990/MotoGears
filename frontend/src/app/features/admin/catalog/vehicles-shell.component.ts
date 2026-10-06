import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

/** Tabs for the three levels of the vehicle database (make → model → variant). */
@Component({
  selector: 'adm-vehicles-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  template: `
    <nav class="tabs" aria-label="Vehicle database">
      <a routerLink="manufacturers" routerLinkActive="active">Manufacturers</a>
      <a routerLink="models" routerLinkActive="active">Models</a>
      <a routerLink="variants" routerLinkActive="active">Variants</a>
    </nav>
    <router-outlet />
  `,
  styles: `.tabs { margin-bottom: 18px; }`,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VehiclesShellComponent {}
