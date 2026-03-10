<?php
header("Content-Type: text/html; charset=utf-8");

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'];
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = $scheme . '://' . $host . $basePath;

$files = glob(__DIR__ . "/*.php");
$endpoints = [];
foreach ($files as $f) {
  $name = basename($f);
  if ($name === "index.php") continue;
  $endpoints[] = $name;
}
sort($endpoints);
$total = count($endpoints);

$denyView = ["config.php"];

if (isset($_GET["raw"])) {
  $raw = basename($_GET["raw"]);
  $ok = in_array($raw, $endpoints, true) && !in_array($raw, $denyView, true);

  if (!$ok) {
    http_response_code(403);
    header("Content-Type: text/plain; charset=utf-8");
    echo "Not allowed";
    exit;
  }

  header("Content-Type: text/plain; charset=utf-8");
  echo file_get_contents(__DIR__ . "/" . $raw);
  exit;
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Food Roulette API</title>
  <style>
    body{font-family:system-ui,sans-serif;margin:0;background:#fff;}
    .wrap{
      display:grid;
      grid-template-columns:1fr 1fr;
      gap:16px;
      padding:12px;
      max-width:1800px;
      margin:0 auto;
      
    }

    .url a{
  color:#2563eb;
  text-decoration:none;
}
.url a:hover{
  text-decoration:underline;
}
    @media (max-width:980px){
      .wrap{grid-template-columns:1fr;}
      .right{position:static;height:auto;}
    }
    h2{margin:0 0 6px;}
    .meta{color:#666;font-size:14px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
    .pill{background:#111;color:#fff;padding:6px 10px;border-radius:999px;font-size:13px;}
    .bar{margin:14px 0 12px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
    input{padding:10px 12px;border:1px solid #ddd;border-radius:10px;width:340px;max-width:100%;}
    table{
      width:100%;
      border-collapse:separate;
      border-spacing:0;
      overflow:hidden;
      border:1px solid #e5e5e5;
      border-radius:12px;
      background:#fff;
      table-layout:fixed;
    }
    th,td{
      padding:12px;
      border-bottom:1px solid #eee;
      text-align:left;
      font-size:14px;
      vertical-align:middle;
    }
    th{background:#fafafa;font-weight:600;}
    tr:last-child td{border-bottom:none;}
    .muted{color:#666;}
    .url{
      word-break:break-word;
      font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
      font-size:13px;
    }
    tbody tr{transition:background .18s ease, transform .18s ease, box-shadow .18s ease;}
    tbody tr.activeRow{
      background:#f3f7ff;
      box-shadow: inset 4px 0 0 #3b82f6;
    }
    tbody tr.flash{animation:flashRow .55s ease;}
    @keyframes flashRow{
      0%{transform:translateY(0);background:#e8f0ff;}
      50%{transform:translateY(-1px);}
      100%{transform:translateY(0);}
    }
    .btn{
      border:1px solid #ddd;
      background:#fff;
      border-radius:10px;
      padding:8px 10px;
      cursor:pointer;
      display:inline-flex;
      gap:8px;
      align-items:center;
      text-decoration:none;
      transition:transform .08s ease;
      user-select:none;
    }
    .btn:hover{background:#f7f7f7;}
    .btn:active{transform:translateY(1px);}
    .copyBtn{
      border:1px solid #ddd;
      background:#fff;
      border-radius:10px;
      padding:6px 10px;
      cursor:pointer;
      font-size:12px;
    }
    .copyBtn:hover{background:#f7f7f7;}
    .right{
      position:sticky;
      top:12px;
      height:calc(100vh - 24px);
      border:1px solid #e5e5e5;
      border-radius:12px;
      overflow:hidden;
      background:#fff;
      display:flex;
      flex-direction:column;
    }
    .panelHead{
      background:#fafafa;
      padding:12px 14px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
      border-bottom:1px solid #eee;
    }
    .panelTitle{display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;}
    .badge{
      display:inline-flex;
      align-items:center;
      font-size:12px;
      padding:4px 10px;
      border-radius:999px;
      background:#111;
      color:#fff;
    }
    .mono{
      font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
    }
    .panelBody{
      background:#0b0f19;
      color:#e6edf3;
      padding:10px;
      overflow:auto;
      flex:1;
    }
    .panelBody pre{margin:0;white-space:pre;}
    .panelBody code{
      background:transparent;
      padding:0;
      border-radius:0;
      font-size:14px;
      font-family:ui-monospace,Menlo,Consolas,monospace;
      line-height:1.45;
    }
    .empty{padding:14px;color:#666;font-size:14px;}
    .toast{
      position:fixed;
      left:50%;
      bottom:18px;
      transform:translateX(-50%);
      background:#111;
      color:#fff;
      padding:10px 12px;
      border-radius:999px;
      font-size:13px;
      opacity:0;
      pointer-events:none;
      transition:opacity .15s ease;
    }
    .toast.show{opacity:1;}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="left">
      <h2>Food Roulette API</h2>
      <div class="meta">
        <span class="pill">Total: <?= $total ?></span>
        <span class="muted">Base: <?= htmlspecialchars($baseUrl) ?></span>
      </div>

      <div class="bar">
        <input id="q" type="text" placeholder="Search endpoint..." oninput="filterRows()">
        <span class="muted" id="shown"></span>
      </div>

      <table id="tbl">
        <thead>
          <tr>
            <th style="width:60px;">#</th>
            <th style="width:220px;">Endpoint</th>
            <th>URL</th>
            <th style="width:140px;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($endpoints as $i => $e): ?>
            <?php $url = $baseUrl . '/' . $e; ?>
            <tr data-file="<?= htmlspecialchars($e) ?>">
              <td class="muted"><?= $i + 1 ?></td>
              <td>
  <a href="<?= htmlspecialchars($url) ?>" target="_blank">
    <b><?= htmlspecialchars($e) ?></b>
  </a>
  <?php if (in_array($e, $denyView, true)): ?>
    <span class="muted"> (Hidden)</span>
  <?php endif; ?>
</td>
          <td class="url">
  <a href="<?= htmlspecialchars($url) ?>" target="_blank">
    <?= htmlspecialchars($url) ?>
  </a><br>
  <button class="copyBtn" type="button" data-copy="<?= htmlspecialchars($url) ?>">คัดลอก URL</button>
</td>
              <td>
                <?php if (!in_array($e, $denyView, true)): ?>
                  <button class="btn" type="button" data-code="<?= htmlspecialchars($e) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                      <path d="M9 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      <path d="M15 6l6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    ดูโค้ด
                  </button>
                <?php else: ?>
                  <span class="muted">Hidden</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="right">
      <div class="panelHead">
        <div class="panelTitle">
          <b>Code</b>
          <span class="badge" id="badge" style="display:none;">
            กำลังดู: <span class="mono" id="badgeFile" style="margin-left:6px;"></span>
          </span>
        </div>
        <div style="display:flex; gap:10px; align-items:center;">
          <button class="btn" type="button" id="copyCodeBtn" style="display:none;">คัดลอกโค้ด</button>
          <button class="btn" type="button" id="closeBtn">ปิด</button>
        </div>
      </div>

      <div class="panelBody" id="panelBody" style="display:none;">
        <pre><code id="codeText"></code></pre>
      </div>

      <div class="empty" id="emptyState">กด “ดูโค้ด” จากตารางด้านซ้าย เพื่อแสดงโค้ดตรงนี้</div>
    </div>
  </div>

  <div class="toast" id="toast">Copied!</div>

  <script>
    function filterRows(){
      const q = document.getElementById('q').value.toLowerCase().trim();
      const rows = document.querySelectorAll('#tbl tbody tr');
      let shown = 0;
      rows.forEach(r=>{
        const text = r.innerText.toLowerCase();
        const ok = text.includes(q);
        r.style.display = ok ? '' : 'none';
        if(ok) shown++;
      });
      document.getElementById('shown').textContent =
        q ? `Showing ${shown} / ${rows.length}` : `Showing ${rows.length} / ${rows.length}`;
    }

    async function copyText(text){
      try {
        await navigator.clipboard.writeText(text);
        showToast("Copied!");
      } catch (e) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        showToast("Copied!");
      }
    }

    let toastTimer = null;
    function showToast(msg){
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(()=>t.classList.remove('show'), 900);
    }

    const badge = document.getElementById('badge');
    const badgeFile = document.getElementById('badgeFile');
    const panelBody = document.getElementById('panelBody');
    const codeText = document.getElementById('codeText');
    const emptyState = document.getElementById('emptyState');
    const copyCodeBtn = document.getElementById('copyCodeBtn');

    let lastActiveRow = null;

    function setActiveRow(file){
      if (lastActiveRow) lastActiveRow.classList.remove('activeRow');
      const row = document.querySelector(`#tbl tbody tr[data-file="${CSS.escape(file)}"]`);
      if (row) {
        row.classList.add('activeRow');
        row.classList.add('flash');
        setTimeout(()=>row.classList.remove('flash'), 650);
        lastActiveRow = row;
      }
    }

    async function openCode(file){
      const y = window.scrollY;
      const leftScroll = document.scrollingElement ? document.scrollingElement.scrollTop : y;

      setActiveRow(file);

      badge.style.display = "inline-flex";
      badgeFile.textContent = file;
      emptyState.style.display = "none";
      panelBody.style.display = "block";
      copyCodeBtn.style.display = "inline-flex";
      codeText.textContent = "Loading...";

      try{
        const res = await fetch(`?raw=${encodeURIComponent(file)}`);
        const txt = await res.text();
        if (!res.ok) throw new Error(txt || "Fetch failed");
        codeText.textContent = txt;
      } catch(e){
        codeText.textContent = "Error: " + e.message;
      }

      window.scrollTo({top: leftScroll, behavior: "instant"});
      history.replaceState({code:file}, "", `?code=${encodeURIComponent(file)}`);
    }

    function closeCode(){
      badge.style.display = "none";
      panelBody.style.display = "none";
      emptyState.style.display = "block";
      copyCodeBtn.style.display = "none";
      codeText.textContent = "";
      if (lastActiveRow) lastActiveRow.classList.remove('activeRow');
      history.replaceState({}, "", "./");
    }

    document.addEventListener('click', (ev) => {
      const codeBtn = ev.target.closest('[data-code]');
      if (codeBtn) openCode(codeBtn.dataset.code || "");

      const copyBtn = ev.target.closest('[data-copy]');
      if (copyBtn) copyText(copyBtn.dataset.copy || "");

      if (ev.target.closest('#copyCodeBtn')) {
        copyText(codeText.innerText || "");
      }
      if (ev.target.closest('#closeBtn')) closeCode();
    });

    (function initFromQuery(){
      const params = new URLSearchParams(location.search);
      const f = params.get("code");
      if (f) openCode(f);
    })();

    filterRows();
  </script>
</body>
</html>