<?php
// gtest.php — TEMPORARY on-device diagnostic for the sliding gallery.
// Renders the same gallery with feature toggles (?mask=0&contain=0&anim=0&aos=0&kb=1)
// and shows live overlap measurements as big readable text. DELETE AFTER USE.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/sys/db_connect.php';
require_once __DIR__ . '/sys/helpers.php';

$gallery = ['land' => [], 'port' => []];
$res = $conn->query("SELECT file_base, orientation FROM gallery_images WHERE is_active = 1 ORDER BY sort_order, id");
while ($row = $res->fetch_assoc()) $gallery[$row['orientation']][] = $row['file_base'];

$T = function ($k, $d) { return (($_GET[$k] ?? $d) === '1'); };
$mask    = $T('mask', '1');
$contain = $T('contain', '1');
$anim    = $T('anim', '1');
$aos     = $T('aos', '1');
$kb      = $T('kb', '0');
$fix     = $T('fix', '0');
$cfg = "mask=" . (int)$mask . " contain=" . (int)$contain . " anim=" . (int)$anim . " aos=" . (int)$aos . " kb=" . (int)$kb . " fix=" . (int)$fix;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>gallery test</title>
<?php if ($aos): ?><link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"><?php endif; ?>
<style>
body { margin: 0; background: #EADECB; font-family: -apple-system, sans-serif; }
#hud { position: sticky; top: 0; z-index: 99; background: #111; color: #fff; padding: 10px 12px; font-size: 15px; line-height: 1.5; }
#hud .bad { color: #ff5f56; font-weight: 700; }
#hud .ok { color: #27c93f; font-weight: 700; }
.gallery-mosaic {
  width: 100%;
  overflow: hidden;
  <?php if ($contain): ?>contain: paint;<?php endif; ?>
  <?php if ($mask): ?>
  -webkit-mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
          mask-image: linear-gradient(to right, transparent 0, #000 6%, #000 94%, transparent 100%);
  <?php endif; ?>
}
.gtrack { display: flex; will-change: transform; }
.gtrack.is-sliding { animation: gslide linear infinite; }
@keyframes gslide { from { transform: translateX(0); } to { transform: translateX(var(--slide-distance, -100%)); } }
.gstrip { display: flex; gap: .85rem; align-items: flex-start; padding-right: .85rem; }
<?php if ($fix): ?>
/* THE FIX under test: Safari shrinks the strip flex-items to the track width,
   making their rigid columns overlap the neighbouring strip. Chrome doesn't. */
.gstrip { flex: 0 0 auto; }
<?php endif; ?>
.gcol { flex: 0 0 165px; display: flex; flex-direction: column; gap: .85rem; }
.gframe { display: block; width: 100%; margin: 0; padding: 0; border: 0; background: #efe7d7; border-radius: 12px; overflow: hidden; cursor: pointer; line-height: 0; position: relative; }
.gframe.landscape { aspect-ratio: 3 / 2; }
.gframe.portrait  { aspect-ratio: 3 / 4; }
.gframe img { width: 100%; height: 100%; object-fit: cover; display: block; }
<?php if ($kb): ?>
.gframe img { animation: gkenburns 16s ease-in-out infinite alternate; }
.gframe:nth-child(2n) img { animation-duration: 19s; animation-delay: -4s; }
.gframe:nth-child(3n) img { animation-duration: 22s; animation-delay: -9s; }
@keyframes gkenburns { from { transform: scale(1); } to { transform: scale(1.12); } }
<?php endif; ?>
</style>
</head>
<body>
<div id="hud">loading…</div>
<div style="height:30px"></div>
<div class="gallery-mosaic" id="gallery-mosaic" <?php if ($aos): ?>data-aos="fade-up"<?php endif; ?>>
<?php foreach (['land', 'port'] as $o):
  $shape = $o === 'port' ? 'portrait' : 'landscape';
  foreach ($gallery[$o] as $base): ?>
<button class="gframe <?= $shape ?>" data-orient="<?= $o ?>" data-base="<?= h($base) ?>" type="button"><img src="assets/img/gallery/<?= h($base) ?>.jpg" alt=""></button>
<?php endforeach; endforeach; ?>
</div>
<div style="height:60vh"></div>
<?php if ($aos): ?><script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script><?php endif; ?>
<script>
(function () {
  var CFG = <?= json_encode($cfg) ?>;
  var ANIM = <?= $anim ? 'true' : 'false' ?>;
  if (window.AOS) AOS.init({ duration: 800, once: true, offset: 80 });

  var gMosaic = document.getElementById('gallery-mosaic');
  var allFrames = Array.prototype.slice.call(gMosaic.querySelectorAll('.gframe'));
  var landFrames = allFrames.filter(function (f) { return f.dataset.orient !== 'port'; });
  var portFrames = allFrames.filter(function (f) { return f.dataset.orient === 'port'; });

  var cols = Math.min(Math.floor(landFrames.length / 2), Math.floor(portFrames.length / 2));
  var baseCols = [];
  for (var c = 0; c < cols; c++) {
    var col = document.createElement('div');
    col.className = 'gcol';
    var cl = landFrames.slice(c * 2, c * 2 + 2);
    var cp = portFrames.slice(c * 2, c * 2 + 2);
    var order = (c % 2 === 0) ? [cl[0], cp[0], cl[1], cp[1]] : [cp[0], cl[0], cp[1], cl[1]];
    order.forEach(function (f) { if (f) col.appendChild(f); });
    baseCols.push(col);
  }
  var strip = document.createElement('div');
  strip.className = 'gstrip';
  strip.appendChild(baseCols[0]);
  gMosaic.innerHTML = '';
  gMosaic.appendChild(strip);
  var colW = baseCols[0].getBoundingClientRect().width;
  var gap = parseFloat(getComputedStyle(strip).columnGap) || 0;
  var step = colW + gap;
  strip.innerHTML = '';
  var repeats = Math.max(1, Math.ceil((gMosaic.getBoundingClientRect().width * 1.15) / (cols * step)));
  for (var r = 0; r < repeats; r++) baseCols.forEach(function (col) { strip.appendChild(r === 0 ? col : col.cloneNode(true)); });
  var setWidth = strip.children.length * step;
  var track = document.createElement('div');
  track.className = 'gtrack';
  track.appendChild(strip);
  track.appendChild(strip.cloneNode(true));
  gMosaic.innerHTML = '';
  gMosaic.appendChild(track);
  if (ANIM) {
    track.style.setProperty('--slide-distance', '-' + setWidth + 'px');
    track.style.animationDuration = (setWidth / 60) + 's';
    track.classList.add('is-sliding');
  }

  function measure() {
    var frames = Array.prototype.slice.call(gMosaic.querySelectorAll('.gframe'));
    var maxSpill = 0, overlaps = 0, worst = '';
    var rects = [];
    frames.forEach(function (f) {
      var fr = f.getBoundingClientRect();
      if (fr.right < -50 || fr.left > innerWidth + 50) return; // only near-viewport
      var im = f.querySelector('img').getBoundingClientRect();
      var spill = Math.max(im.width - fr.width, im.height - fr.height, fr.left - im.left, im.right - fr.right);
      if (spill > maxSpill) maxSpill = spill;
      rects.push(fr);
    });
    for (var i = 0; i < rects.length; i++) {
      for (var j = i + 1; j < rects.length; j++) {
        var a = rects[i], b = rects[j];
        var ox = Math.min(a.right, b.right) - Math.max(a.left, b.left);
        var oy = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
        if (ox > 3 && oy > 3) { overlaps++; if (!worst) worst = Math.round(ox) + 'x' + Math.round(oy) + 'px'; }
      }
    }
    var spillTxt = maxSpill > 2 ? '<span class="bad">SPILL ' + Math.round(maxSpill) + 'px</span>' : '<span class="ok">SPILL 0</span>';
    var ovTxt = overlaps > 0 ? '<span class="bad">OVERLAPS ' + overlaps + ' (' + worst + ')</span>' : '<span class="ok">NO OVERLAP</span>';
    document.getElementById('hud').innerHTML =
      '<b>' + CFG + '</b><br>' + spillTxt + ' · ' + ovTxt +
      '<br>cols=' + cols + ' colW=' + Math.round(colW) + ' gap=' + Math.round(gap) + ' vw=' + innerWidth + ' frames=' + rects.length;
  }
  setInterval(measure, 1500);
  measure();
})();
</script>
</body>
</html>
