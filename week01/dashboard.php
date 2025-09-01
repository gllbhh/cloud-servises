<?php
// dash helpers
function sh($cmd) { return trim(shell_exec($cmd . " 2>/dev/null")); }
function read_first($path) { return @file_exists($path) ? trim(@file_get_contents($path)) : ""; }
function bytes_fmt($bytes) {
  $units = ['B','KB','MB','GB','TB','PB']; $i=0;
  while ($bytes>=1024 && $i<count($units)-1) { $bytes/=1024; $i++; }
  return sprintf("%.1f %s", $bytes, $units[$i]);
}
// collect metrics
function metrics() {
  // Uptime
  $uptime_s = floatval(explode(" ", read_first("/proc/uptime"))[0] ?? 0);
  $uptime = sh("uptime -p") ?: sprintf("up ~%d min", round($uptime_s/60));

  // Load averages
  $load = explode(" ", read_first("/proc/loadavg"));
  $load1 = $load[0] ?? "0.00"; $load5 = $load[1] ?? "0.00"; $load15 = $load[2] ?? "0.00";

  // CPU count
  $cpus = intval(sh("nproc") ?: 1);

  // Memory
  $meminfo = @file("/proc/meminfo", FILE_IGNORE_NEW_LINES) ?: [];
  $mem = [];
  foreach ($meminfo as $line) { list($k,$v) = array_map('trim', explode(":", $line)); $mem[$k] = floatval($v); }
  // MemTotal/Available in kB; SwapTotal/Free in kB
  $memTotal = ($mem['MemTotal'] ?? 0) * 1024;
  $memAvail = ($mem['MemAvailable'] ?? 0) * 1024;
  $memUsed  = max(0, $memTotal - $memAvail);

  $swapTotal = ($mem['SwapTotal'] ?? 0) * 1024;
  $swapFree  = ($mem['SwapFree'] ?? 0) * 1024;
  $swapUsed  = max(0, $swapTotal - $swapFree);

  // Disk (root filesystem)
  $diskTotal = @disk_total_space("/") ?: 0;
  $diskFree  = @disk_free_space("/") ?: 0;
  $diskUsed  = max(0, $diskTotal - $diskFree);

  // Network (IPv4 addresses)
  $ip4 = array_filter(array_map('trim',
    explode("\n", sh("ip -4 -o addr show | awk '{print \$2\": \"\$4}'"))));

  // Top processes by CPU
  $procs = explode("\n", sh("ps -eo pid,comm,%cpu,%mem --sort=-%cpu | head -n 6"));

  // Apache status (basic)
  $apache = [
    "active" => sh("systemctl is-active apache2"),
    "since"  => sh("systemctl show -p ActiveEnterTimestamp apache2 | cut -d= -f2")
  ];

  return [
    "uptime" => $uptime,
    "load" => ["1m"=>$load1, "5m"=>$load5, "15m"=>$load15, "cpus"=>$cpus],
    "memory" => [
      "total" => $memTotal, "used" => $memUsed, "free" => $memAvail,
      "swap_total" => $swapTotal, "swap_used" => $swapUsed
    ],
    "disk" => ["total"=>$diskTotal, "used"=>$diskUsed, "free"=>$diskFree, "mount"=>"/"],
    "network" => array_values($ip4),
    "processes" => $procs,
    "apache" => $apache,
    "hostname" => gethostname(),
    "time" => date("Y-m-d H:i:s")
  ];
}

// JSON mode for ajax refresh
if (isset($_GET['json'])) {
  header('Content-Type: application/json');
  echo json_encode(metrics());
  exit;
}

$data = metrics();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Mini VM Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root { --fg:#111; --muted:#555; --ok:#2e7d32; --warn:#e65100; --bad:#c62828; --card:#f7f7f8; }
    body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; color: var(--fg); background: #fff; }
    header { padding: 16px 20px; border-bottom: 1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
    h1 { font-size: 20px; margin: 0; }
    .grid { display:grid; gap:16px; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); padding:20px; }
    .card { background: var(--card); border:1px solid #eee; border-radius:10px; padding:14px; }
    .kpi { font-size: 26px; font-weight:700; margin: 6px 0; }
    .row { display:flex; justify-content:space-between; margin:4px 0; font-feature-settings:"tnum"; font-variant-numeric: tabular-nums; }
    .muted { color: var(--muted); }
    code, pre { background:#fff; border:1px solid #eee; border-radius:6px; padding:6px; display:block; overflow:auto; }
    .pill { display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid #ddd; background:#fff; }
    .ok { color: var(--ok); } .warn { color: var(--warn); } .bad { color: var(--bad); }
    footer { padding: 0 20px 20px; color: var(--muted); }
    button { padding:8px 12px; border-radius:8px; border:1px solid #ddd; background:#fff; cursor:pointer; }
  </style>
</head>
<body>
<header>
  <h1>Mini VM Dashboard — <?=htmlspecialchars($data["hostname"])?> </h1>
  <div>
    <span class="pill" id="clock"><?=htmlspecialchars($data["time"])?></span>
    <button id="refresh">Refresh</button>
  </div>
</header>

<div class="grid" id="cards">
  <div class="card">
    <div class="muted">Uptime</div>
    <div class="kpi"><?=htmlspecialchars($data["uptime"])?></div>
  </div>

  <div class="card">
    <div class="muted">Load Average (per <?=intval($data["load"]["cpus"])?> CPU)</div>
    <div class="row"><span>1 min</span><span class="<?=($data["load"]["1m"]/$data["load"]["cpus"]>1?'bad':($data["load"]["1m"]/$data["load"]["cpus"]>0.7?'warn':'ok'))?>"><?=htmlspecialchars($data["load"]["1m"])?></span></div>
    <div class="row"><span>5 min</span><span><?=htmlspecialchars($data["load"]["5m"])?></span></div>
    <div class="row"><span>15 min</span><span><?=htmlspecialchars($data["load"]["15m"])?></span></div>
  </div>

  <div class="card">
    <div class="muted">Memory</div>
    <div class="row"><span>Used</span><span><?=bytes_fmt($data["memory"]["used"])?> / <?=bytes_fmt($data["memory"]["total"])?></span></div>
    <div class="row"><span>Free</span><span><?=bytes_fmt($data["memory"]["free"])?></span></div>
    <div class="row"><span>Swap</span><span><?=bytes_fmt($data["memory"]["swap_used"])?> / <?=bytes_fmt($data["memory"]["swap_total"])?></span></div>
  </div>

  <div class="card">
    <div class="muted">Disk (<?=htmlspecialchars($data["disk"]["mount"])?>)</div>
    <div class="kpi"><?=bytes_fmt($data["disk"]["used"])?> / <?=bytes_fmt($data["disk"]["total"])?></div>
    <div class="row"><span>Free</span><span><?=bytes_fmt($data["disk"]["free"])?></span></div>
  </div>

  <div class="card">
    <div class="muted">Network (IPv4)</div>
    <?php if ($data["network"]) { foreach ($data["network"] as $ip) { ?>
      <div class="row"><span><?=$ip?></span></div>
    <?php }} else { ?>
      <div class="muted">No IPv4 address detected.</div>
    <?php } ?>
  </div>

  <div class="card">
    <div class="muted">Apache</div>
    <div class="row"><span>Status</span><span class="<?=($data["apache"]["active"]==='active'?'ok':'bad')?>"><?=htmlspecialchars($data["apache"]["active"])?></span></div>
    <div class="row"><span>Since</span><span><?=htmlspecialchars($data["apache"]["since"])?></span></div>
  </div>

  <div class="card">
    <div class="muted">Top processes (CPU)</div>
    <pre><?php echo htmlspecialchars(implode("\n", $data["processes"])); ?></pre>
  </div>
</div>

<footer>
  Auto-updates every 5s. You can hard-refresh if commands change.
</footer>

<script>
  const clock = document.getElementById('clock');
  const btn = document.getElementById('refresh');

  async function refresh() {
    try {
      const r = await fetch(location.pathname + '?json=1', {cache:'no-store'});
      if (!r.ok) throw new Error('fetch failed');
      const d = await r.json();
      clock.textContent = d.time;
      // Replace the cards block by reloading just the HTML (simple approach):
      // We fetch the full page, slice out the cards HTML, and swap it in.
      const html = await (await fetch(location.pathname, {cache:'no-store'})).text();
      const tmp = document.createElement('div'); tmp.innerHTML = html;
      const newCards = tmp.querySelector('#cards');
      if (newCards) document.getElementById('cards').replaceWith(newCards);
    } catch(e) {
      console.error(e);
    }
  }
  btn.addEventListener('click', refresh);
  setInterval(refresh, 5000);
</script>
</body>
</html>
