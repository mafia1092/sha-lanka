<?php
// gtest.php — on-device diagnostic for the 2-ROW sliding gallery loop.
// Replicates the production construction and monitors, over time, the worst
// empty gap ever shown at either edge of each row — one screenshot proves
// whether the loop ever breaks on THIS device. ?fast=1 = 3s cycles.
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
#hud { position: sticky; top: 0; z-index: 99; background: #111; color: #fff; padding: 12px; font-size: 16px; line-height: 1.5; }
#hud .bad { color: #ff5f56; font-weight: 700; }
#hud .ok { color: #27c93f; font-weight: 700; }
.gallery-mosaic { position: relative; width: 100%; overflow: hidden; contain: paint; }
.grow { display: flex; width: max-content; }
.grow + .grow { margin-top: .85rem; }
.grow.is-sliding { animation: gslide linear infinite; }
@keyframes gslide { from { transform: translateX(0); } to { transform: translateX(-50%); } }
.gseq { display: flex; flex: 0 0 auto; gap: .85rem; padding-right: .85rem; }
.gframe { display: block; flex: 0 0 auto; min-width: 0; margin: 0; padding: 0; border: 0; background: #efe7d7; border-radius: 12px; overflow: hidden; line-height: 0; height: 260px; }
.gframe.landscape { width: 390px; max-width: 390px; }
.gframe.portrait  { width: 195px; max-width: 195px; }
.gframe img { width: 100%; height: 100%; object-fit: cover; display: block; }
@media (max-width: 899px) {
  .gframe { height: 160px; }
  .gframe.landscape { width: 240px; max-width: 240px; }
  .gframe.portrait  { width: 120px; max-width: 120px; }
}
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
  var zipped = [];
  var n = Math.max(land.length, port.length);
  for (var i = 0; i < n; i++) { if (land[i]) zipped.push(land[i]); if (port[i]) zipped.push(port[i]); }
  var rowsFrames = [[], []];
  zipped.forEach(function (f, j) { rowsFrames[Math.floor(j / 2) % 2].push(f); });

  m.innerHTML = '';
  var mosaicW = m.getBoundingClientRect().width;
  var buildInfo = 'AT BUILD: mosaicW=' + Math.round(mosaicW) + ' innerW=' + window.innerWidth;
  var rows = [], seqWAtBuild = [];
  rowsFrames.forEach(function (frames, r) {
    var seq = document.createElement('div'); seq.className = 'gseq';
    frames.forEach(function (f) { seq.appendChild(f); });
    var row = document.createElement('div'); row.className = 'grow';
    row.appendChild(seq); m.appendChild(row);
    var guard = 0;
    while (seq.getBoundingClientRect().width < mosaicW * 1.15 && guard < 4) {
      frames.forEach(function (f) { seq.appendChild(f.cloneNode(true)); });
      guard++;
    }
    seqWAtBuild.push(Math.round(seq.getBoundingClientRect().width));
    row.appendChild(seq.cloneNode(true));
    var dur = FAST ? 3 : (row.getBoundingClientRect().width / 2) / 60;
    row.style.animationDuration = dur + 's';
    row.classList.add('is-sliding');
    rows.push(row);
  });
  buildInfo += ' seq0=' + seqWAtBuild[0] + ' seq1=' + seqWAtBuild[1];

  var worstGap = 0, samples = 0, deepestTx = 0;
  var startedAt = Date.now();
  function measure() {
    // Warm-up: Safari animates new tabs into place, garbling early rects.
    if (Date.now() - startedAt < 2500) { return; }
    var W = m.getBoundingClientRect().width;
    var nowW = [];
    rows.forEach(function (row) {
      var tr = row.getBoundingClientRect();
      nowW.push(Math.round(tr.width / 2));
      var tx = 0;
      try {
        var mtx = getComputedStyle(row).transform;
        if (mtx && mtx !== 'none') tx = (new DOMMatrixReadOnly(mtx)).e;
      } catch (e) {}
      if (tx < deepestTx) deepestTx = tx;
      var g = Math.max(W - tr.right, tr.left, 0);
      if (g > worstGap) worstGap = g;
    });
    samples++;
    var tag = worstGap > 2
      ? '<span class="bad">BROKEN — beige gap up to ' + Math.round(worstGap) + 'px</span>'
      : '<span class="ok">SEAMLESS — no gap ever</span>';
    document.getElementById('hud').innerHTML =
      tag + '<br>' + buildInfo +
      '<br>NOW: seq0=' + nowW[0] + ' seq1=' + nowW[1] + ' vw=' + Math.round(W) +
      ' deepestTx=' + Math.round(deepestTx) + ' samples=' + samples;
  }
  setInterval(measure, 200);
})();
</script>
</body>
</html>
