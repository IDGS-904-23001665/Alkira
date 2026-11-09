param([string]$Nombre='ALKIRA-HU07-Publicaciones')
$ErrorActionPreference='Stop'
$tarea=Get-ScheduledTask -TaskName $Nombre -ErrorAction SilentlyContinue
if(-not $tarea){Write-Host "La tarea no existe: $Nombre";exit 0}
Unregister-ScheduledTask -TaskName $Nombre -Confirm:$false
Write-Host "Tarea eliminada: $Nombre" -ForegroundColor Green
