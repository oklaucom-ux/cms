$source = "C:\Users\cyno_\OneDrive\Desktop\cms"
$destination = "C:\Users\cyno_\OneDrive\Desktop\cms\cms_cpanel_deploy_v2.zip"
$excludeList = @("cms_cpanel_deploy.zip", "cms_cpanel_deploy_v2.zip", "database.sqlite", "scaffold.py", "Cyno Mangment system.html", "package.ps1", ".gemini", "backups")

$tempDir = Join-Path $source "temp_build"
If (Test-Path $tempDir) { Remove-Item -Path $tempDir -Recurse -Force }
New-Item -ItemType Directory -Force -Path $tempDir | Out-Null

Get-ChildItem -Path $source | Where-Object { $excludeList -notcontains $_.Name } | Copy-Item -Destination $tempDir -Recurse -Force -ErrorAction SilentlyContinue

Compress-Archive -Path "$tempDir\*" -DestinationPath $destination -Force
Remove-Item -Path $tempDir -Recurse -Force

Write-Host "Packaging Complete!"
