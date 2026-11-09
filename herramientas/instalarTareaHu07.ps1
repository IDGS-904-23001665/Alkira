# Ejecuta este script por separado SOLO cuando decidas activar la publicacion automatica.
# NO envia correos/push si no existe su configuracion local habilitada.
param([string]$Proyecto='C:\xampp\htdocs\Alkira',
      [string]$Php='C:\xampp\php\php.exe',
      [string]$Nombre='ALKIRA-HU07-Publicaciones')
$ErrorActionPreference='Stop'
if(-not (Test-Path -LiteralPath $Php -PathType Leaf)){throw "No existe PHP: $Php"}
$worker=Join-Path $Proyecto 'backend\cron\procesarHu07.php'
if(-not (Test-Path -LiteralPath $worker -PathType Leaf)){throw "No existe el trabajador HU07 en $Proyecto"}
if(Get-ScheduledTask -TaskName $Nombre -ErrorAction SilentlyContinue){throw "Ya existe la tarea $Nombre. No se sobrescribio."}
$accion=New-ScheduledTaskAction -Execute $Php -Argument ('-d date.timezone=America/Mexico_City "{0}"' -f $worker) -WorkingDirectory $Proyecto
$disparador=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1) -RepetitionDuration (New-TimeSpan -Days 3650)
$usuario=[Security.Principal.WindowsIdentity]::GetCurrent().Name
$principal=New-ScheduledTaskPrincipal -UserId $usuario -LogonType Interactive -RunLevel Limited
$ajustes=New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 3) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName $Nombre -Action $accion -Trigger $disparador -Principal $principal -Settings $ajustes -Description 'Publica avisos programados HU07 y procesa entregas habilitadas.' | Out-Null
Write-Host "Tarea creada: $Nombre" -ForegroundColor Green
Write-Host "Se ejecuta cada minuto mientras $usuario tenga una sesion abierta en Windows."
Write-Host "Para comprobar: Get-ScheduledTaskInfo -TaskName '$Nombre'"
