import { Component, OnInit } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { UpdateService } from './services/update.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet],
  templateUrl: './app.component.html',
  styleUrl: './app.component.scss',
})
export class AppComponent implements OnInit {
  title = 'frontend';

  constructor(private updateService: UpdateService) {}

  ngOnInit(): void {
    // O UpdateService já é inicializado automaticamente via providedIn: 'root'
    // Mas podemos forçar uma verificação inicial se necessário
  }
}
