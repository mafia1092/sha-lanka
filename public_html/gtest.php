<?php
// gtest.php — TEMPORARY on-device diagnostic for the sliding gallery loop.
// Mirrors the PRODUCTION build (translateX(-50%) + width:max-content) and
// monitors, over time, the worst empty gap ever shown at either edge — so a
// single screenshot proves whether the loop ever exposes beige (a broken
// wrap) on THIS device. ?fast=1 runs a 3s cycle to see many wraps quickly.
// DELETE AFTER USE.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
require_once __DIR__ . '/sys/db_connect.php';
require_once __DIR__ . '/sys/helpers.php';

$gallery = ['land' => [], 'port' => []];
$res = $conn->query("SELECT file_base, orientation FROM gallery_images WHERE is_active = 1 ORDER BY sort_order, id");
while ($row = $res->fetch_assoc()) $gallery[$row['orientation']][] = $row['file_base'];
$fast = (($_GET['fast'] ?? '') === '1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>loop test</title>
<style>
body { margin: 0; background: #EADECB; font-family: -apple-system, sans-serif; }
#hud { position: sticky; top: 0; z-index: 99; background: #111; color: #fff; padding: 12px; font-size: 17px; line-height: 1.5; }
#hud .bad { color: #ff5f56; font-weight: 700; }
#hud .ok { color: #27c93f; font-weight: 700; }
.gallery-mosaic { position: relative; width: 100%; overflow: hidden; contain: paint; }
.gtrack { display: flex; width: max-content; }
.gtrack.is-sliding { animation: gslide linear infinite; }
@keyframes gslide { from { transform: translateX(0); } to { transform: translateX(-50%); } }
.gstrip { display: flex; flex: 0 0 auto; min-width: 0; gap: .85rem; align-items: flex-start; padding-right: .85rem; }
/* min-width:0 is THE fix under test: without it, WebKit lets each image's
   natural width (700px thumbnails) inflate its 165px column once loaded. */
.gcol { flex: 0 0 165px; min-width: 0; max-width: 165px; display: flex; flex-direction: column; gap: .85rem; }
.gframe { display: block; width: 100%; margin: 0; padding: 0; border: 0; background: #efe7d7; border-radius: 12px; overflow: hidden; line-height: 0; }
.gframe.landscape { aspect-ratio: 3 / 2; }
.gframe.portrait  { aspect-ratio: 3 / 4; }
.gframe img { width: 100%; height: 100%; object-fit: cover; display: block; }
</style>
</head>
<body>
<div id="hud">measuring…</div>
<div style="height:20px"></div>
<div class="gallery-mosaic" id="gallery-mosaic">
<?php foreach (['land', 'port'] as $o):
  $shape = $o === 'port' ? 'portrait' : 'landscape';
  foreach ($gallery[$o] as $base): ?>
<button class="gframe <?= $shape ?>" data-orient="<?= $o ?>" data-base="<?= h($base) ?>" type="button"><img src="assets/img/gallery/<?= h($base) ?>.jpg" alt=""></button>
<?php endforeach; endforeach; ?>
</div>
<div style="height:60vh"></div>
<script>
(function () {
  var FAST = <?= $fast ? 'true' : 'false' ?>;
  var m = document.getElementById('gallery-mosaic');
  var allFrames = Array.prototype.slice.call(m.querySelectorAll('.gframe'));
  var land = allFrames.filter(function(f){ return f.dataset.orient !== 'port'; });
  var port = allFrames.filter(function(f){ return f.dataset.orient === 'port'; });

  var cols = Math.min(Math.floor(land.length/2), Math.floor(port.length/2));
  var baseCols = [];
  for (var c = 0; c < cols; c++) {
    var col = document.createElement('div'); col.className = 'gcol';
    var cl = land.slice(c*2, c*2+2), cp = port.slice(c*2, c*2+2);
    var order = (c%2===0) ? [cl[0],cp[0],cl[1],cp[1]] : [cp[0],cl[0],cp[1],cl[1]];
    order.forEach(function(f){ if (f) col.appendChild(f); });
    baseCols.push(col);
  }
  var strip = document.createElement('div'); strip.className = 'gstrip';
  strip.appendChild(baseCols[0]); m.innerHTML=''; m.appendChild(strip);
  var colW = baseCols[0].getBoundingClientRect().width;
  var gap = parseFloat(getComputedStyle(strip).columnGap) || 0;
  var step = colW + gap; strip.innerHTML='';
  // Capture what THIS engine believed the widths were at build time — this is
  // the number that decides `repeats`, so if it is inflated we see it here.
  var mosaicWAtBuild = m.getBoundingClientRect().width;
  var repeats = Math.max(1, Math.ceil((mosaicWAtBuild*1.15)/(cols*step)));
  var buildInfo = 'AT BUILD: mosaicW=' + Math.round(mosaicWAtBuild)
    + ' innerW=' + window.innerWidth
    + ' clientW=' + document.documentElement.clientWidth
    + ' repeats=' + repeats;
  for (var r=0; r<repeats; r++) baseCols.forEach(function(col){ strip.appendChild(r===0?col:col.cloneNode(true)); });
  var track = document.createElement('div'); track.className='gtrack';
  track.appendChild(strip); track.appendChild(strip.cloneNode(true));
  m.innerHTML=''; m.appendChild(track);
  var dur = FAST ? 3 : (track.getBoundingClientRect().width/2)/60;
  track.style.animationDuration = dur + 's';
  track.classList.add('is-sliding');

  var worstGap = 0, samples = 0, deepestTx = 0;
  var startedAt = Date.now();
  function measure() {
    // Warm-up: Safari animates new tabs into place, which garbles early rect
    // reads. Judge only steady-state.
    if (Date.now() - startedAt < 2500) { return; }
    var W = m.getBoundingClientRect().width;
    var tr = track.getBoundingClientRect();
    // the ACTUAL animated offset right now, straight from the engine
    var tx = 0;
    try {
      var mtx = getComputedStyle(track).transform;
      if (mtx && mtx !== 'none') tx = (new DOMMatrixReadOnly(mtx)).e;
    } catch (e) {}
    if (tx < deepestTx) deepestTx = tx;
    var gapRight = W - tr.right;
    var gapLeft  = tr.left;
    var g = Math.max(gapRight, gapLeft, 0);
    if (g > worstGap) worstGap = g;
    samples++;
    var tag = worstGap > 2
      ? '<span class="bad">BROKEN — beige gap up to ' + Math.round(worstGap) + 'px</span>'
      : '<span class="ok">SEAMLESS — no gap ever</span>';
    document.getElementById('hud').innerHTML =
      tag + '<br>' + buildInfo +
      '<br>NOW: track=' + Math.round(tr.width) + ' strip0=' + Math.round(track.children[0].getBoundingClientRect().width) +
      ' vw=' + Math.round(W) + ' innerW=' + window.innerWidth +
      '<br>deepest translateX=' + Math.round(deepestTx) + 'px (expected max -' + Math.round(tr.width/2) + ') samples=' + samples;
  }
  setInterval(measure, 200);
  measure();
})();
</script>
</body>
</html>
