Add-Type -AssemblyName System.Drawing

$size = 512
$bmp = New-Object System.Drawing.Bitmap($size, $size)
$g = [System.Drawing.Graphics]::FromImage($bmp)
$g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
$g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
$g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
$g.Clear([System.Drawing.Color]::Transparent)

# Draw Circular Gradient Background (Navy / Deep Blue)
$rect = New-Object System.Drawing.Rectangle(0, 0, $size, $size)
$brush = New-Object System.Drawing.Drawing2D.LinearGradientBrush($rect, [System.Drawing.Color]::FromArgb(255, 11, 79, 196), [System.Drawing.Color]::FromArgb(255, 0, 42, 119), 45.0)
$g.FillEllipse($brush, 24, 24, 464, 464)

# Draw White Headset Customer Service Icon
$whitePen = New-Object System.Drawing.Pen([System.Drawing.Color]::White, 28)
$whitePen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
$whitePen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round

# Headband arc
$arcRect = New-Object System.Drawing.Rectangle(136, 120, 240, 240)
$g.DrawArc($whitePen, $arcRect, 180, 180)

# Left earpad
$whiteBrush = New-Object System.Drawing.SolidBrush([System.Drawing.Color]::White)
$g.FillEllipse($whiteBrush, 120, 210, 44, 88)

# Right earpad
$g.FillEllipse($whiteBrush, 348, 210, 44, 88)

# Mic boom curve & microphone
$micPen = New-Object System.Drawing.Pen([System.Drawing.Color]::White, 20)
$micPen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
$micPen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
$g.DrawArc($micPen, 210, 230, 160, 140, 0, 100)

# Microphone capsule
$g.FillEllipse($whiteBrush, 216, 344, 42, 28)

# Inner support person face silhouette / smile
$smilePen = New-Object System.Drawing.Pen([System.Drawing.Color]::FromArgb(200, 255, 255, 255), 14)
$smilePen.StartCap = [System.Drawing.Drawing2D.LineCap]::Round
$smilePen.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
$g.DrawArc($smilePen, 210, 220, 92, 70, 20, 140)

# Save as assets/icon-cs.png
$bmp.Save('assets/icon-cs.png', [System.Drawing.Imaging.ImageFormat]::Png)

$g.Dispose()
$bmp.Dispose()
Write-Output "Generated clean Customer Service headset icon at assets/icon-cs.png"
