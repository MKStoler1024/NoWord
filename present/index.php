<?php
if (!file_exists(__DIR__ . '/../config.json')) {
  header('Location: /install.php');
  exit;
}
$config = json_decode(file_get_contents(__DIR__ . '/../config.json'), true);

$pdo = null;
if ($config['db_type'] === 'mysql') {
  $dsn = "mysql:host={$config['mysql']['host']};dbname={$config['mysql']['db']};charset=utf8mb4";
  $pdo = new PDO($dsn, $config['mysql']['user'], $config['mysql']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
} else {
  $sqlite_path = $config['sqlite'];
  if (!preg_match('/^([a-zA-Z]:)?[\/\\\\]/', $sqlite_path)) {
    $sqlite_path = realpath(__DIR__ . '/../' . $sqlite_path);
    if ($sqlite_path === false) {
      $sqlite_path = __DIR__ . '/../' . $config['sqlite'];
    }
  }
  if (!file_exists($sqlite_path)) {
    die('SQLite 数据库文件不存在，请检查路径或重新初始化。');
  }
  $dsn = "sqlite:$sqlite_path";
  $pdo = new PDO($dsn, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
  ]);
}
$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM schemes WHERE id=?");
$stmt->execute([$id]);
$scheme = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$scheme) exit('方案不存在');
$data = json_decode($scheme['data'], true);
$settings = $data['settings'] ?? [];
$words = $data['words'] ?? [];
if (!is_array($words)) $words = [];
$rounds = $settings['rounds'] ?? 1;
$repeat = $settings['repeat'] ?? 1;
$wait = $settings['wait'] ?? 1;
$show_cn = !empty($settings['show_cn']);
$shuffle = !empty($settings['shuffle']);
$show_phonetic = !isset($settings['show_phonetic']) || $settings['show_phonetic'];
$font_size = $settings['font_size'] ?? 36;
?>
<!DOCTYPE html>
<html lang="zh-cn">
<head>
  <meta charset="UTF-8">
  <title>放映 - <?= htmlspecialchars($scheme['name']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://unpkg.com/@material/web@1.0.0/dist/material-web.min.css">
  <style>
    html, body {
      height:100%;
      margin:0;
      padding:0;
      background: linear-gradient(135deg, #e8f5e9 0%, #f5fff5 100%);
      color: #222;
      font-family: system-ui, sans-serif;
    }
    body {
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      min-height:100vh;
    }
    #main {
      width:100vw;
      height:100vh;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
    }
    .word {
      font-size: <?= intval($font_size) ?>px;
      font-weight: bold;
      margin-bottom: 0.5em;
      color: #388e3c;
      letter-spacing: 0.04em;
      text-shadow: 0 2px 8px rgba(56,142,60,0.08);
    }
    .phonetic {
      font-size: <?= intval($font_size/2) ?>px;
      color: #43a047;
      margin-bottom: 0.5em;
      letter-spacing: 0.02em;
    }
    .cn {
      font-size: <?= intval($font_size/2.2) ?>px;
      color: #666;
      margin-bottom: 1em;
      letter-spacing: 0.01em;
    }
    .topbar {
      position:fixed;
      top:0;
      left:0;
      width:auto;
      min-width:320px;
      max-width:98vw;
      display:flex;
      justify-content:flex-start;
      align-items:center;
      padding:1em 2em 1em 2em;
      background: rgba(232,245,233,0.95);
      box-shadow: 0 2px 8px rgba(56,142,60,0.07);
      z-index: 10;
      /* 新增：防止超宽 */
      right:auto;
    }
    .progress {
      color:#388e3c;
      font-size:1.1em;
      font-weight: 600;
      letter-spacing: 0.03em;
      margin-left: 1em;
      margin-right: 0.5em;
      white-space:nowrap;
      max-width: 60vw;
      overflow: hidden;
      text-overflow: ellipsis;
      display: inline-block;
      vertical-align: middle;
    }
    .controls {
      position:fixed;
      bottom:2em;
      left:0;
      width:100vw;
      display:flex;
      justify-content:center;
      gap:2em;
      z-index: 10;
    }
    .btn {
      background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);
      color:#fff;
      border:none;
      border-radius:10px;
      padding:0.7em 2em;
      font-size:1.1em;
      font-weight: 600;
      letter-spacing: 0.04em;
      cursor:pointer;
      box-shadow: 0 2px 8px rgba(56,142,60,0.10);
      transition: background .2s, box-shadow .2s, transform .2s;
    }
    .btn:hover {
      background: linear-gradient(90deg, #388e3c 60%, #43a047 100%);
      box-shadow: 0 4px 16px rgba(56,142,60,0.18);
      transform: scale(1.04);
    }
    .fs-btn {
      background:transparent;
      border:none;
      color:#388e3c;
      font-size:1.5em;
      cursor:pointer;
      transition: color .2s;
    }
    .fs-btn:hover {
      color: #2e7031;
    }
    .font-size-bar {
      position:fixed;
      right:2em;
      bottom:2em;
      background:#fff;
      border-radius:8px;
      box-shadow:0 2px 8px rgba(56,142,60,0.10);
      padding:0.5em 1em;
      font-size: 1.05em;
      color: #388e3c;
      border: 1.5px solid #c8e6c9;
      z-index: 10;
      display: flex;
      align-items: center;
      gap: 0.5em;
    }
    .font-size-bar input[type="range"] {
      accent-color: #43a047;
    }
    @media (max-width: 700px) {
      .topbar, .controls, .font-size-bar { padding-left: 0.5em; padding-right: 0.5em; }
      .font-size-bar { right: 0.5em; bottom: 0.5em; }
    }
    @media (prefers-color-scheme: dark) {
      html, body, #main { background: linear-gradient(135deg, #1a1f1a 0%, #263238 100%); color: #eee; }
      .topbar { background: rgba(38,50,56,0.97); }
      .word { color: #66bb6a; text-shadow: 0 2px 8px rgba(56,142,60,0.13);}
      .phonetic { color: #81c784; }
      .cn { color: #bbb; }
      .progress { color: #66bb6a; }
      .font-size-bar { background: #232d23; color: #66bb6a; border: 1.5px solid #37474f; }
      .btn { background: linear-gradient(90deg, #388e3c 60%, #43a047 100%); }
      .btn:hover { background: linear-gradient(90deg, #43a047 60%, #66bb6a 100%);}
      .fs-btn { color: #66bb6a; }
      .fs-btn:hover { color: #43a047; }
    }
  </style>
</head>
<body>
  <div class="topbar">
    <button class="fs-btn" id="fs-btn" title="全屏" style="margin-right:1em;">⛶</button>
    <span class="progress" id="progress"></span>
  </div>
  <div id="main">
    <div class="word" id="word"></div>
    <?php if ($show_phonetic): ?>
    <div class="phonetic" id="phonetic"></div>
    <?php endif; ?>
    <?php if ($show_cn): ?>
    <div class="cn" id="cn"></div>
    <?php endif; ?>
  </div>
  <div class="controls" id="controls" style="display:none;">
    <button class="btn" id="done-btn" onclick="location.href='/'">完成</button>
    <button class="btn" id="restart-btn" onclick="restart()">再来一遍</button>
    <button class="btn" id="pause-btn" onclick="togglePause()">暂停</button>
  </div>
  <div class="font-size-bar">
    字号 <input type="range" min="18" max="120" value="<?= intval($font_size) ?>" id="font-size-range" style="vertical-align:middle;">
    <span id="font-size-val"><?= intval($font_size) ?></span>
  </div>
  <script>
    const wordsRaw = <?= json_encode($words, JSON_UNESCAPED_UNICODE) ?>;
    let words = [];
    let rounds = <?= intval($rounds) ?>;
    let repeat = <?= intval($repeat) ?>;
    let wait = <?= intval($wait) ?> * 1000;
    let show_cn = <?= $show_cn ? 'true' : 'false' ?>;
    let show_phonetic = <?= $show_phonetic ? 'true' : 'false' ?>;
    let shuffle = <?= $shuffle ? 'true' : 'false' ?>;
    let fontSize = <?= intval($font_size) ?>;
    let initialFontSize = fontSize;
    let total = 0, idx = 0, round = 1;
    let seq = [];
    let _pause = false;
    let _timer = null;
    let _pendingPlay = null;
    let _countdownTimer = null;

    function shuffleArr(arr) {
      for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
      }
    }
    function buildSeq() {
      seq = [];
      let oneRound = [];
      let widx = Array.from({length: words.length}, (_,i)=>i);
      if (shuffle) shuffleArr(widx);
      for (let i of widx) {
        for (let t = 0; t < repeat; ++t) oneRound.push(i);
      }
      for (let r = 0; r < rounds; ++r) {
        let roundSeq = oneRound.slice();
        if (shuffle && r > 0) shuffleArr(roundSeq);
        seq = seq.concat(roundSeq);
      }
      total = seq.length;
    }

    function showCountdown(cb) {
      let countdown = 3;
      document.getElementById('controls').style.display = 'none';
      document.getElementById('progress').textContent = '';
      document.getElementById('word').textContent = '';
      if (show_phonetic) document.getElementById('phonetic').textContent = '';
      if (show_cn) document.getElementById('cn').textContent = '';
      function tick() {
        if (countdown > 0) {
          document.getElementById('word').textContent = countdown;
          countdown--;
          _countdownTimer = setTimeout(tick, 1000);
        } else {
          document.getElementById('word').textContent = '';
          if (show_phonetic) document.getElementById('phonetic').textContent = '';
          if (show_cn) document.getElementById('cn').textContent = '';
          cb && cb();
        }
      }
      tick();
    }

    function setControlsMode(mode) {
      // mode: 'playing' | 'done'
      const doneBtn = document.getElementById('done-btn');
      const restartBtn = document.getElementById('restart-btn');
      if (mode === 'playing') {
        doneBtn.style.display = 'none';
        restartBtn.style.display = 'none';
      } else {
        doneBtn.style.display = '';
        restartBtn.style.display = '';
      }
    }

    function showFinishTip() {
      const wordDiv = document.getElementById('word');
      wordDiv.innerHTML = '<span style="color:#fff;background:#43a047;padding:0.5em 1.5em;border-radius:16px;font-size:1.3em;box-shadow:0 2px 16px #43a04755;">🎉 已完成本轮词汇播放！</span>';
      if (show_phonetic) document.getElementById('phonetic').textContent = '';
      if (show_cn) document.getElementById('cn').textContent = '';
    }

    function getProgressText(idx) {
      // idx: 当前播放到的序号（从0开始）
      // 只统计已出现过的词汇数量
      let seen = new Set();
      for (let i = 0; i <= idx && i < seq.length; ++i) {
        seen.add(seq[i]);
      }
      return `已读 ${seen.size} / ${words.length} 个`;
    }

    async function preloadAllAudio(cb) {
      // 预加载所有音频
      let audios = [];
      let loaded = 0, total = 0;
      let audioUrls = new Set();
      for (const w of words) {
        if (w.uk_audio) audioUrls.add(w.uk_audio);
        if (w.us_audio) audioUrls.add(w.us_audio);
      }
      total = audioUrls.size;
      if (total === 0) { cb && cb(); return; }
      let tipDiv = document.getElementById('word');
      tipDiv.innerHTML = '<span style="color:#fff;background:#388e3c;padding:0.5em 1.5em;border-radius:12px;font-size:1.1em;">正在预加载音频，请稍候...</span>';
      let finished = false;
      let done = () => {
        if (!finished) {
          finished = true;
          tipDiv.innerHTML = '';
          cb && cb();
        }
      };
      let count = 0;
      for (const url of audioUrls) {
        const audio = new Audio();
        audio.preload = 'auto';
        audio.src = url;
        audio.oncanplaythrough = audio.onerror = function() {
          loaded++;
          if (loaded === total) setTimeout(done, 200);
        };
        // 触发加载
        audio.load();
        audios.push(audio);
        count++;
      }
      // 超时兜底
      setTimeout(done, 6000);
    }

    function play(idx) {
      if (_pause) {
        _pendingPlay = () => play(idx);
        return;
      }
      if (words.length === 0) {
        document.getElementById('progress').textContent = '';
        document.getElementById('word').textContent = '（方案词汇为空）';
        if (show_phonetic) document.getElementById('phonetic').textContent = '';
        if (show_cn) document.getElementById('cn').textContent = '';
        document.getElementById('controls').style.display = '';
        setControlsMode('done');
        return;
      }
      if (idx >= total) {
        document.getElementById('controls').style.display = '';
        setControlsMode('done');
        document.getElementById('progress').innerHTML = `<span style="color:#43a047;font-weight:bold;">已读 ${words.length} / ${words.length} 个</span>`;
        showFinishTip();
        window.scrollTo(0,0);
        return;
      }
      let w = words[seq[idx]];
      document.getElementById('controls').style.display = '';
      setControlsMode('playing');
      document.getElementById('progress').textContent = getProgressText(idx);
      document.getElementById('word').textContent = w.word;
      if (show_phonetic) document.getElementById('phonetic').textContent = w.uk_phonetic || w.us_phonetic || '';
      if (show_cn) document.getElementById('cn').textContent = w.cn || '';

      if (!window._audioErrorUrlSet) window._audioErrorUrlSet = new Set();

      function playAudio(url, cb) {
        if (!url || window._audioErrorUrlSet.has(url)) { cb && cb(); return; }
        let audio = new Audio(url);
        audio.onerror = function(e) {
          if (!window._audioErrorLog) window._audioErrorLog = [];
          window._audioErrorLog.push({url, time: Date.now()});
          window._audioErrorUrlSet.add(url);
          cb && cb();
        };
        audio.onended = function() { cb && cb(); };
        try {
          audio.play().catch(function(){ cb && cb(); });
        } catch(e) {
          cb && cb();
        }
      }

      // 修复暂停后继续无法恢复播放
      function nextStep() {
        if (_pause) {
          _pendingPlay = () => nextStep();
        } else if (idx + 1 < total) {
          play(idx+1);
        } else {
          document.getElementById('controls').style.display = '';
          setControlsMode('done');
          document.getElementById('progress').innerHTML = `<span style="color:#43a047;font-weight:bold;">已读 ${words.length} / ${words.length} 个</span>`;
          showFinishTip();
          window.scrollTo(0,0);
        }
      }

      // 播放英音后等待，再播美音
      playAudio(w.uk_audio, function() {
        setTimeout(function() {
          playAudio(w.us_audio, function() {
            _timer = setTimeout(nextStep, wait);
          });
        }, wait);
      });
    }

    function restart() {
      document.getElementById('controls').style.display = '';
      setControlsMode('playing');
      fontSize = initialFontSize;
      document.getElementById('font-size-range').value = fontSize;
      document.getElementById('font-size-val').textContent = fontSize;
      document.getElementById('word').style.fontSize = fontSize + 'px';
      if (document.getElementById('phonetic')) document.getElementById('phonetic').style.fontSize = (fontSize/2) + 'px';
      if (document.getElementById('cn')) document.getElementById('cn').style.fontSize = (fontSize/2.2) + 'px';
      buildSeq();
      window._audioErrorUrlSet = new Set();
      _pause = false;
      if (_timer) { clearTimeout(_timer); _timer = null; }
      if (_countdownTimer) { clearTimeout(_countdownTimer); _countdownTimer = null; }
      document.getElementById('pause-btn').textContent = '暂停';
      showCountdown(() => play(0));
    }

    function togglePause() {
      _pause = !_pause;
      let btn = document.getElementById('pause-btn');
      if (_pause) {
        btn.textContent = '继续';
        if (_timer) { clearTimeout(_timer); _timer = null; }
      } else {
        btn.textContent = '暂停';
        if (_pendingPlay) {
          let fn = _pendingPlay;
          _pendingPlay = null;
          fn();
        }
      }
    }

    document.getElementById('font-size-range').addEventListener('input', function() {
      fontSize = parseInt(this.value);
      document.getElementById('font-size-val').textContent = fontSize;
      document.getElementById('word').style.fontSize = fontSize + 'px';
      if (document.getElementById('phonetic')) document.getElementById('phonetic').style.fontSize = (fontSize/2) + 'px';
      if (document.getElementById('cn')) document.getElementById('cn').style.fontSize = (fontSize/2.2) + 'px';
    });

    document.getElementById('fs-btn').onclick = function() {
      let el = document.documentElement;
      if (!document.fullscreenElement) el.requestFullscreen();
      else document.exitFullscreen();
    };

    // 初始化
    words = wordsRaw;
    buildSeq();
    preloadAllAudio(() => {
      showCountdown(() => play(0));
    });
  </script>
</body>
</html>