Add-Type -AssemblyName System.Drawing

$navy = [System.Drawing.Color]::FromArgb(15, 43, 70)
$gold = [System.Drawing.Color]::FromArgb(212, 175, 55)
$white = [System.Drawing.Color]::White
$muted = [System.Drawing.Color]::FromArgb(100, 116, 139)

function New-Brush([System.Drawing.Color]$color) {
  return New-Object System.Drawing.SolidBrush($color)
}

function Draw-RoundedRect($graphics, $brush, $x, $y, $width, $height, $radius) {
  $path = New-Object System.Drawing.Drawing2D.GraphicsPath
  $diameter = $radius * 2
  $path.AddArc($x, $y, $diameter, $diameter, 180, 90)
  $path.AddArc(($x + $width - $diameter), $y, $diameter, $diameter, 270, 90)
  $path.AddArc(($x + $width - $diameter), ($y + $height - $diameter), $diameter, $diameter, 0, 90)
  $path.AddArc($x, ($y + $height - $diameter), $diameter, $diameter, 90, 90)
  $path.CloseFigure()
  $graphics.FillPath($brush, $path)
  $path.Dispose()
}

function Draw-Symbol($graphics, $goldBrush, $whiteBrush, $navyBrush) {
  # Croix : foi et identité de l'église.
  $graphics.FillRectangle($goldBrush, 492, 178, 40, 130)
  $graphics.FillRectangle($goldBrush, 458, 218, 108, 38)

  # Barres ascendantes : suivi financier et transparence.
  Draw-RoundedRect $graphics $whiteBrush 352 455 78 170 18
  Draw-RoundedRect $graphics $whiteBrush 472 395 78 230 18
  Draw-RoundedRect $graphics $whiteBrush 592 335 78 290 18

  # Livre ouvert : partenariat, formation et enseignement.
  $leftPage = @(
    (New-Object System.Drawing.Point(512, 600)),
    (New-Object System.Drawing.Point(292, 650)),
    (New-Object System.Drawing.Point(292, 790)),
    (New-Object System.Drawing.Point(512, 740))
  )
  $rightPage = @(
    (New-Object System.Drawing.Point(512, 600)),
    (New-Object System.Drawing.Point(732, 650)),
    (New-Object System.Drawing.Point(732, 790)),
    (New-Object System.Drawing.Point(512, 740))
  )
  $graphics.FillPolygon($whiteBrush, $leftPage)
  $graphics.FillPolygon($whiteBrush, $rightPage)

  $pen = New-Object System.Drawing.Pen($navy, 8)
  $pen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
  $pen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
  $graphics.DrawLine($pen, 512, 608, 512, 742)
  $graphics.DrawLine($pen, 330, 690, 470, 658)
  $graphics.DrawLine($pen, 330, 730, 470, 700)
  $graphics.DrawLine($pen, 694, 658, 554, 690)
  $graphics.DrawLine($pen, 694, 700, 554, 730)
  $pen.Dispose()
}

function Draw-AppIcon($graphics, $x, $y, $size, [bool]$withBackground) {
  $state = $graphics.Save()
  $graphics.TranslateTransform($x, $y)
  $graphics.ScaleTransform(($size / 1024), ($size / 1024))

  $goldBrush = New-Brush $gold
  $whiteBrush = New-Brush $white
  $navyBrush = New-Brush $navy

  if ($withBackground) {
    $graphics.FillRectangle($navyBrush, 0, 0, 1024, 1024)
  }

  $ringPen = New-Object System.Drawing.Pen($gold, 28)
  $graphics.DrawEllipse($ringPen, 112, 112, 800, 800)
  $ringPen.Dispose()

  Draw-Symbol $graphics $goldBrush $whiteBrush $navyBrush

  $goldBrush.Dispose()
  $whiteBrush.Dispose()
  $navyBrush.Dispose()
  $graphics.Restore($state)
}

function Save-Bitmap($bitmap, $path) {
  $bitmap.Save($path, [System.Drawing.Imaging.ImageFormat]::Png)
  $bitmap.Dispose()
}

$assets = Split-Path -Parent $MyInvocation.MyCommand.Path

# Icône Android classique (carrée).
$icon = New-Object System.Drawing.Bitmap(1024, 1024, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
$graphics = [System.Drawing.Graphics]::FromImage($icon)
$graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
$graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
Draw-AppIcon $graphics 0 0 1024 $true
Save-Bitmap $icon (Join-Path $assets 'icon.png')
$graphics.Dispose()

# Icône adaptive Android (fond transparent, couleur définie dans app.json).
$adaptive = New-Object System.Drawing.Bitmap(1024, 1024, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
$graphics = [System.Drawing.Graphics]::FromImage($adaptive)
$graphics.Clear([System.Drawing.Color]::Transparent)
$graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
$graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
Draw-AppIcon $graphics 0 0 1024 $false
Save-Bitmap $adaptive (Join-Path $assets 'adaptive-icon.png')
$graphics.Dispose()

# Logo horizontal.
$logo = New-Object System.Drawing.Bitmap(1600, 640, [System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
$graphics = [System.Drawing.Graphics]::FromImage($logo)
$graphics.Clear($white)
$graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
$graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$graphics.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::ClearTypeGridFit
Draw-AppIcon $graphics 70 80 480 $true

$navyBrush = New-Brush $navy
$goldBrush = New-Brush $gold
$mutedBrush = New-Brush $muted
$titleFont = New-Object System.Drawing.Font('Segoe UI', 96, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
$taglineFont = New-Object System.Drawing.Font('Segoe UI', 38, [System.Drawing.FontStyle]::Regular, [System.Drawing.GraphicsUnit]::Pixel)

$graphics.DrawString('FinanceMobile', $titleFont, $navyBrush, 600, 205)
$graphics.FillRectangle($goldBrush, 606, 330, 620, 8)
$graphics.DrawString('Partenariat et dons', $taglineFont, $mutedBrush, 610, 365)

$titleFont.Dispose()
$taglineFont.Dispose()
$navyBrush.Dispose()
$goldBrush.Dispose()
$mutedBrush.Dispose()
Save-Bitmap $logo (Join-Path $assets 'logo.png')
$graphics.Dispose()

Write-Output 'Assets générés : icon.png, adaptive-icon.png, logo.png'
