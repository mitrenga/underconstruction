<?php
// tell search engines the site is temporarily down for maintenance:
// 503 + Retry-After = "come back later, keep the old index" (Google's
// recommended signal for maintenance mode), X-Robots-Tag/noindex = don't
// index this placeholder page itself. 200 is kept for the notify-form POST
// so the submission isn't reported as a server error.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(503);
  header('Retry-After: 86400');   // suggest re-crawling in a day
}
header('X-Robots-Tag: noindex, nofollow');

// store the e-mail for the launch notification
// anti-spam: honeypot field + signed render-time token (min 4 s, max 24 h)
// + per-IP rate limit — same trio as retrogames/sendFeedback.php
$tsKey = __DIR__;
$tsNow = time();
$tsToken = $tsNow . '.' . hash_hmac('sha256', (string)$tsNow, $tsKey);

$notifySaved = false;
$notifyError = false;
$notifyErrorMsg = 'Please enter a valid e-mail address.';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
  $email = trim($_POST['email']);
  $honeypot = trim((string)($_POST['website'] ?? ''));   // hidden field, humans leave it empty
  [$ts, $sig] = array_pad(explode('.', (string)($_POST['ts'] ?? ''), 2), 2, '');

  $tooFast = false;
  $tooOld = false;
  $isBot = $honeypot !== ''
        || $ts === '' || !hash_equals(hash_hmac('sha256', $ts, $tsKey), $sig);
  if (!$isBot) {
    $tooFast = (int)$ts > time() - 4;
    $tooOld  = (int)$ts < time() - 86400;
  }

  // at most 5 submissions per IP and hour
  $rateLimited = false;
  if (!$isBot && !$tooFast && !$tooOld) {
    $rateFile = sys_get_temp_dir() . '/underconstruction-notify-' . md5(__DIR__) . '.json';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $now = time();
    $log = is_file($rateFile) ? (json_decode((string)file_get_contents($rateFile), true) ?: []) : [];
    $log = array_values(array_filter($log, fn($e) => ($e['time'] ?? 0) > $now - 3600));
    if (count(array_filter($log, fn($e) => ($e['ip'] ?? '') === $ip)) >= 5) {
      $rateLimited = true;
    } else {
      $log[] = ['ip' => $ip, 'time' => $now];
      file_put_contents($rateFile, json_encode($log), LOCK_EX);
    }
  }

  if ($isBot) {
    $notifySaved = true;                                 // pretend success to bots
  } elseif ($tooFast) {
    $notifyError = true;
    $notifyErrorMsg = 'That was quick! Please take a moment and try again.';
  } elseif ($tooOld) {
    $notifyError = true;
    $notifyErrorMsg = 'This page was open for too long, please reload it and try again.';
  } elseif ($rateLimited) {
    $notifyError = true;
    $notifyErrorMsg = 'Too many submissions, please try again later.';
  } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 254) {
    $file = __DIR__ . '/notify-emails.json';
    $list = [];
    if (is_file($file)) {
      $decoded = json_decode((string)file_get_contents($file), true);
      if (is_array($decoded)) { $list = $decoded; }
    }
    $already = false;
    foreach ($list as $item) {
      if (isset($item['email']) && strcasecmp($item['email'], $email) === 0) { $already = true; break; }
    }
    if (!$already) {
      $list[] = ['date' => date('c'), 'email' => $email];
      file_put_contents($file, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL, LOCK_EX);
    }
    $notifySaved = true;
  } else {
    $notifyError = true;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Under Construction</title>
<style>
  :root {
    --sky-top: #1b2a4a;
    --sky-bottom: #3e5c8f;
    --accent: #ffc93c;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html { height: 100%; }
  body {
    min-height: 100%;
    font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
    background: linear-gradient(180deg, var(--sky-top) 0%, var(--sky-bottom) 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    overflow-x: hidden; /* vertical scroll stays available as a fallback on tiny screens */
    color: #fff;
    padding: 1rem 0;
  }
  .scene {
    display: flex;
    justify-content: center;
    width: 100%;
  }
  /* scene width limited by window width, 900px, and available height
     (aspect ratio 1200:620; ~210px reserved for the title, subtitle and button) */
  svg {
    display: block;
    width: min(92vw, 900px, max(280px, calc((100vh - 210px) * 1200 / 620)));
    height: auto;
    aspect-ratio: 1200 / 620;
  }

  h1 {
    margin-top: 1.2rem;
    font-size: clamp(1.4rem, 4vw, 2.6rem);
    letter-spacing: 0.35em;
    text-indent: 0.35em;
    font-weight: 800;
    text-transform: uppercase;
    text-align: center;
    padding: 0 0.5rem;
  }
  h1 span { color: var(--accent); }
  p.sub {
    margin-top: 0.7rem;
    font-size: clamp(0.85rem, 2vw, 1.05rem);
    opacity: 0.75;
    letter-spacing: 0.08em;
    text-align: center;
    padding: 0 1rem;
  }
  .dots span {
    display: inline-block;
    width: 0.5em; height: 0.5em;
    margin-left: 0.3em;
    border-radius: 50%;
    background: var(--accent);
    animation: bounce 1.2s infinite;
  }
  .dots span:nth-child(2) { animation-delay: 0.15s; }
  .dots span:nth-child(3) { animation-delay: 0.3s; }
  @keyframes bounce {
    0%, 60%, 100% { transform: translateY(0); opacity: 0.5; }
    30% { transform: translateY(-0.35em); opacity: 1; }
  }

  /* ---------- SVG animations ---------- */
  svg * { transform-box: fill-box; }

  .cloud { animation: drift linear infinite; }
  .cloud--1 { animation-duration: 55s; }
  .cloud--2 { animation-duration: 75s; animation-delay: -30s; }
  .cloud--3 { animation-duration: 95s; animation-delay: -60s; }
  @keyframes drift {
    from { transform: translateX(-350px); }
    to   { transform: translateX(1300px); }
  }

  .stars circle { animation: twinkle 3s ease-in-out infinite; }
  .stars circle:nth-child(2n)  { animation-delay: 0.8s; }
  .stars circle:nth-child(3n)  { animation-delay: 1.6s; }
  @keyframes twinkle {
    0%, 100% { opacity: 0.25; }
    50% { opacity: 1; }
  }

  /* --- load cycle: belt → crane → scaffolding → crane → belt (16 s) ---
     positions: belt tx=0 ty=250 | hoisted ty=0 | scaffolding tx=150 ty=122
     rope: scaleY = 1 + ty/80 */

  /* trolley travels along the jib */
  .trolley { animation: trolley-move 16s ease-in-out infinite; }
  @keyframes trolley-move {
    0%, 25%   { transform: translateX(0); }
    36%, 57%  { transform: translateX(150px); }
    68%, 100% { transform: translateX(0); }
  }

  /* rope lowers and raises */
  .hoist {
    transform-origin: 50% 0%;
    animation: hoist-move 16s ease-in-out infinite;
  }
  @keyframes hoist-move {
    0%, 12%   { transform: translateX(0)     scaleY(1); }     /* up, load arriving */
    18%       { transform: translateX(0)     scaleY(4.125); } /* down to the belt */
    25%       { transform: translateX(0)     scaleY(1); }     /* lift */
    36%       { transform: translateX(150px) scaleY(1); }     /* travel to the scaffolding */
    43%, 50%  { transform: translateX(150px) scaleY(2.525); } /* placing on the scaffolding */
    57%       { transform: translateX(150px) scaleY(1); }     /* lift off the scaffolding */
    68%       { transform: translateX(0)     scaleY(1); }     /* travel back */
    74%       { transform: translateX(0)     scaleY(4.125); } /* down onto the belt */
    79%, 100% { transform: translateX(0)     scaleY(1); }     /* magnet up, load departing */
  }

  /* electromagnet follows the rope end */
  .hook { animation: hook-move 16s ease-in-out infinite; }
  @keyframes hook-move {
    0%, 12%   { transform: translate(0, 0); }
    18%       { transform: translate(0, 250px); }
    25%       { transform: translate(0, 0); }
    36%       { transform: translate(150px, 0); }
    43%, 50%  { transform: translate(150px, 122px); }
    57%       { transform: translate(150px, 0); }
    68%       { transform: translate(0, 0); }
    74%       { transform: translate(0, 250px); }
    79%, 100% { transform: translate(0, 0); }
  }

  /* load: along the belt from the right edge, by crane to the scaffolding and back, by belt to the right edge */
  .load { animation: load-move 16s ease-in-out infinite; }
  @keyframes load-move {
    0%        { transform: translate(288px, 250px); } /* waiting at the right edge of the belt */
    12%, 18%  { transform: translate(0, 250px); }     /* arrived under the crane */
    25%       { transform: translate(0, 0); }         /* lift */
    36%       { transform: translate(150px, 0); }     /* travel */
    43%, 50%  { transform: translate(150px, 122px); } /* resting on the scaffolding */
    57%       { transform: translate(150px, 0); }     /* lift off the scaffolding */
    68%       { transform: translate(0, 0); }         /* travel back */
    74%, 79%  { transform: translate(0, 250px); }     /* placed on the belt */
    95%, 100% { transform: translate(288px, 250px); } /* departed to the right edge, where it waits */
  }


  /* beacon blinks */
  .beacon { animation: blink 1.6s ease-in-out infinite; }
  @keyframes blink {
    0%, 100% { opacity: 0.15; }
    50% { opacity: 1; }
  }
  .beacon-glow { animation: glow 1.6s ease-in-out infinite; }
  @keyframes glow {
    0%, 100% { opacity: 0; transform: scale(0.6); }
    50% { opacity: 0.5; transform: scale(1); }
  }

  /* windows light up one after another */
  .win { animation: light-up 8s ease-in-out infinite; }
  .win:nth-child(2n) { animation-delay: 2s; }
  .win:nth-child(3n) { animation-delay: 4s; }
  .win:nth-child(5n) { animation-delay: 6s; }
  @keyframes light-up {
    0%, 100% { fill: #22355c; }
    50% { fill: #ffd97a; }
  }

  /* worker digging: the arm with the pickaxe rotates around the shoulder as one unit */
  .arm {
    transform-box: view-box;
    transform-origin: 146px 518px; /* shoulder */
    animation: arm-dig 1.4s ease-in-out infinite;
  }
  @keyframes arm-dig {
    0%, 100% { transform: rotate(0deg); }
    45%, 55% { transform: rotate(-50deg); }
  }

  .spark { animation: spark-pop 1.4s ease-out infinite; opacity: 0; }
  @keyframes spark-pop {
    0%, 40% { opacity: 0; transform: translate(0,0) scale(0.4); }
    50% { opacity: 1; }
    70% { opacity: 0; transform: translate(-8px,-14px) scale(1); }
    100% { opacity: 0; }
  }

  /* conveyor: runs left on arrival, reversed (right) on departure */
  .belt-strip {
    stroke-dasharray: 8 6;
    animation: belt-run 16s ease-in-out infinite;
  }
  @keyframes belt-run {
    0%        { stroke-dashoffset: 0; }   /* arrival: belt runs left */
    12%, 79%  { stroke-dashoffset: 280; } /* belt stopped */
    95%, 100% { stroke-dashoffset: 0; }   /* departure: belt runs right */
  }

  /* e-mail notification */
  .notify {
    margin-top: 1.4rem;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
    padding: 0 1rem;
    min-height: 3rem;
  }
  .notify-btn {
    font: inherit;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: #1b2a4a;
    background: var(--accent);
    border: none;
    border-radius: 999px;
    padding: 0.7rem 1.6rem;
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
  }
  .notify-btn:hover { transform: translateY(-2px); box-shadow: 0 7px 18px rgba(0, 0, 0, 0.45); }
  .notify-btn:active { transform: translateY(0); }
  .notify form {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
    justify-content: center;
  }
  .notify form[hidden], .notify-btn[hidden] { display: none; }
  .notify input[type="email"] {
    font: inherit;
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 999px;
    padding: 0.65rem 1.2rem;
    width: min(70vw, 300px);
    outline: none;
  }
  .notify input[type="email"]::placeholder { color: rgba(255, 255, 255, 0.5); }
  .notify input[type="email"]:focus { border-color: var(--accent); }
  .notify-msg {
    font-size: 1rem;
    letter-spacing: 0.05em;
  }
  .notify-msg.ok { color: #8fdca4; }
  .notify-msg.err { color: #ff9b78; }

  @media (prefers-reduced-motion: reduce) {
    svg *, .dots span { animation: none !important; }
  }
</style>
</head>
<body>

<div class="scene">
<svg viewBox="0 0 1200 620" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Animated illustration of a construction site with a crane">
  <defs>
    <linearGradient id="bldg" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#54759e"/>
      <stop offset="1" stop-color="#3d5a82"/>
    </linearGradient>
    <linearGradient id="bldg2" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#46648c"/>
      <stop offset="1" stop-color="#324e74"/>
    </linearGradient>
  </defs>

  <!-- stars -->
  <g class="stars" fill="#fff">
    <circle cx="90" cy="60" r="2"/><circle cx="220" cy="120" r="1.5"/>
    <circle cx="400" cy="50" r="2"/><circle cx="640" cy="90" r="1.5"/>
    <circle cx="860" cy="45" r="2"/><circle cx="1050" cy="110" r="1.5"/>
    <circle cx="1140" cy="60" r="2"/><circle cx="520" cy="140" r="1.3"/>
  </g>

  <!-- moon -->
  <circle cx="1040" cy="95" r="42" fill="#f4e9c9" opacity="0.95"/>
  <circle cx="1055" cy="82" r="9" fill="#e2d5ac"/>
  <circle cx="1028" cy="108" r="6" fill="#e2d5ac"/>

  <!-- clouds -->
  <g class="cloud cloud--1" fill="#ffffff" opacity="0.15">
    <ellipse cx="60" cy="150" rx="60" ry="20"/><ellipse cx="110" cy="140" rx="45" ry="16"/>
  </g>
  <g class="cloud cloud--2" fill="#ffffff" opacity="0.12">
    <ellipse cx="40" cy="230" rx="70" ry="22"/><ellipse cx="100" cy="220" rx="50" ry="17"/>
  </g>
  <g class="cloud cloud--3" fill="#ffffff" opacity="0.1">
    <ellipse cx="30" cy="80" rx="50" ry="16"/><ellipse cx="75" cy="72" rx="35" ry="12"/>
  </g>

  <!-- distant city skyline -->
  <g fill="#26395e" opacity="0.8">
    <rect x="0" y="380" width="80" height="180"/>
    <rect x="95" y="340" width="60" height="220"/>
    <rect x="1000" y="360" width="70" height="200"/>
    <rect x="1090" y="320" width="90" height="240"/>
    <rect x="920" y="410" width="60" height="150"/>
  </g>

  <!-- ground -->
  <rect x="0" y="560" width="1200" height="60" fill="#243755"/>
  <rect x="0" y="556" width="1200" height="6" fill="#2e4568"/>

  <!-- building under construction -->
  <g>
    <rect x="620" y="250" width="240" height="310" fill="url(#bldg)"/>
    <!-- unfinished top floor - frame -->
    <g stroke="#6e8cb4" stroke-width="8" fill="none">
      <line x1="632" y1="250" x2="632" y2="190"/>
      <line x1="710" y1="250" x2="710" y2="190"/>
      <line x1="790" y1="250" x2="790" y2="190"/>
      <line x1="848" y1="250" x2="848" y2="190"/>
      <line x1="624" y1="192" x2="856" y2="192"/>
    </g>
    <!-- windows -->
    <g>
      <rect class="win" x="645" y="290" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="700" y="290" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="755" y="290" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="810" y="290" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="645" y="360" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="700" y="360" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="755" y="360" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="810" y="360" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="645" y="430" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="700" y="430" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="755" y="430" width="34" height="40" fill="#22355c"/>
      <rect class="win" x="810" y="430" width="34" height="40" fill="#22355c"/>
    </g>
    <!-- entrance -->
    <rect x="715" y="500" width="50" height="60" fill="#1c2c4a"/>
    <!-- scaffolding -->
    <g stroke="#c98f3d" stroke-width="5" fill="none" opacity="0.9">
      <line x1="600" y1="560" x2="600" y2="270"/>
      <line x1="880" y1="560" x2="880" y2="270"/>
      <line x1="596" y1="330" x2="884" y2="330"/>
      <line x1="596" y1="410" x2="884" y2="410"/>
      <line x1="596" y1="490" x2="884" y2="490"/>
      <line x1="600" y1="330" x2="660" y2="410"/>
      <line x1="880" y1="330" x2="820" y2="410"/>
    </g>
  </g>

  <!-- second smaller building -->
  <g>
    <rect x="160" y="400" width="150" height="160" fill="url(#bldg2)"/>
    <g stroke="#5d7ca6" stroke-width="6" fill="none">
      <line x1="172" y1="400" x2="172" y2="360"/>
      <line x1="235" y1="400" x2="235" y2="360"/>
      <line x1="298" y1="400" x2="298" y2="360"/>
      <line x1="165" y1="362" x2="305" y2="362"/>
    </g>
    <rect class="win" x="180" y="430" width="26" height="32" fill="#22355c"/>
    <rect class="win" x="222" y="430" width="26" height="32" fill="#22355c"/>
    <rect class="win" x="264" y="430" width="26" height="32" fill="#22355c"/>
    <rect class="win" x="180" y="490" width="26" height="32" fill="#22355c"/>
    <rect class="win" x="222" y="490" width="26" height="32" fill="#22355c"/>
    <rect class="win" x="264" y="490" width="26" height="32" fill="#22355c"/>
  </g>

  <!-- conveyor -->
  <g>
    <rect x="330" y="538" width="570" height="16" rx="8" fill="#2b3a55" stroke="#4a628a" stroke-width="2"/>
    <circle cx="346" cy="546" r="4" fill="#4a628a"/>
    <circle cx="384" cy="546" r="4" fill="#4a628a"/>
    <circle cx="422" cy="546" r="4" fill="#4a628a"/>
    <circle cx="460" cy="546" r="4" fill="#4a628a"/>
    <circle cx="498" cy="546" r="4" fill="#4a628a"/>
    <circle cx="536" cy="546" r="4" fill="#4a628a"/>
    <circle cx="574" cy="546" r="4" fill="#4a628a"/>
    <circle cx="612" cy="546" r="4" fill="#4a628a"/>
    <circle cx="650" cy="546" r="4" fill="#4a628a"/>
    <circle cx="688" cy="546" r="4" fill="#4a628a"/>
    <circle cx="726" cy="546" r="4" fill="#4a628a"/>
    <circle cx="764" cy="546" r="4" fill="#4a628a"/>
    <circle cx="802" cy="546" r="4" fill="#4a628a"/>
    <circle cx="840" cy="546" r="4" fill="#4a628a"/>
    <circle cx="878" cy="546" r="4" fill="#4a628a"/>
    <line class="belt-strip" x1="334" y1="538" x2="896" y2="538" stroke="#7d95ba" stroke-width="3"/>
    <rect x="342" y="554" width="8" height="6" fill="#22355c"/>
    <rect x="880" y="554" width="8" height="6" fill="#22355c"/>
  </g>

  <!-- CRANE -->
  <g>
    <!-- tower (lattice) -->
    <g stroke="#ffc93c" stroke-width="7" fill="none">
      <line x1="430" y1="560" x2="430" y2="150"/>
      <line x1="470" y1="560" x2="470" y2="150"/>
      <line x1="430" y1="530" x2="470" y2="490"/>
      <line x1="470" y1="530" x2="430" y2="490"/>
      <line x1="430" y1="450" x2="470" y2="410"/>
      <line x1="470" y1="450" x2="430" y2="410"/>
      <line x1="430" y1="370" x2="470" y2="330"/>
      <line x1="470" y1="370" x2="430" y2="330"/>
      <line x1="430" y1="290" x2="470" y2="250"/>
      <line x1="470" y1="290" x2="430" y2="250"/>
      <line x1="430" y1="210" x2="470" y2="170"/>
      <line x1="470" y1="210" x2="430" y2="170"/>
      <line x1="426" y1="490" x2="474" y2="490"/>
      <line x1="426" y1="410" x2="474" y2="410"/>
      <line x1="426" y1="330" x2="474" y2="330"/>
      <line x1="426" y1="250" x2="474" y2="250"/>
      <line x1="426" y1="170" x2="474" y2="170"/>
    </g>
    <!-- base -->
    <rect x="405" y="548" width="90" height="16" rx="4" fill="#e0a92e"/>

    <!-- slewing arm -->
    <g class="crane-arm">
      <!-- cab -->
      <rect x="418" y="118" width="64" height="42" rx="6" fill="#e0a92e"/>
      <rect x="428" y="126" width="26" height="18" rx="3" fill="#9ed3ff"/>
      <!-- front jib -->
      <g stroke="#ffc93c" stroke-width="6" fill="none">
        <line x1="482" y1="130" x2="800" y2="130"/>
        <line x1="482" y1="152" x2="800" y2="148"/>
        <line x1="520" y1="130" x2="545" y2="151"/>
        <line x1="570" y1="151" x2="595" y2="130"/>
        <line x1="620" y1="130" x2="645" y2="150"/>
        <line x1="670" y1="150" x2="695" y2="130"/>
        <line x1="720" y1="130" x2="745" y2="149"/>
        <line x1="770" y1="149" x2="798" y2="130"/>
        <!-- jib tip end cap -->
        <line x1="798" y1="126" x2="798" y2="152"/>
      </g>
      <rect x="794" y="124" width="10" height="30" rx="3" fill="#e0a92e"/>
      <!-- counterweight -->
      <g stroke="#ffc93c" stroke-width="6" fill="none">
        <line x1="418" y1="130" x2="310" y2="130"/>
        <line x1="418" y1="152" x2="310" y2="150"/>
      </g>
      <rect x="300" y="118" width="46" height="46" fill="#c98f3d"/>
      <!-- apex + tie rods -->
      <path d="M450 118 L450 70 L800 128" stroke="#ffc93c" stroke-width="4" fill="none"/>
      <path d="M450 70 L312 128" stroke="#ffc93c" stroke-width="4" fill="none"/>
      <!-- warning beacon on the apex -->
      <g>
        <circle class="beacon-glow" cx="450" cy="58" r="16" fill="#ff5a4e"/>
        <circle class="beacon" cx="450" cy="58" r="7" fill="#ff5a4e"/>
        <rect x="446" y="63" width="8" height="8" fill="#e0a92e"/>
      </g>

      <!-- trolley + rope + load -->
      <g class="trolley">
        <rect x="560" y="148" width="30" height="12" rx="3" fill="#e0a92e"/>
      </g>
      <g class="hoist">
        <line class="hoist-line" x1="575" y1="160" x2="575" y2="240" stroke="#d8d8d8" stroke-width="3"/>
      </g>
      <!-- electromagnet at the rope end -->
      <g class="hook">
        <rect x="571" y="236" width="8" height="8" fill="#e0a92e"/>
        <rect x="557" y="242" width="36" height="10" rx="4" fill="#d64545"/>
        <rect x="559" y="250" width="32" height="4" rx="2" fill="#9fb3cc"/>
      </g>
      <g class="load">
        <!-- load: a panel -->
        <rect x="540" y="254" width="72" height="34" rx="4" fill="#c9573f"/>
        <rect x="540" y="254" width="72" height="10" rx="4" fill="#a8442f"/>
      </g>
    </g>
  </g>

  <!-- worker with a pickaxe -->
  <g>
    <!-- pile -->
    <path d="M60 560 q40 -34 90 0 z" fill="#3a5378"/>
    <!-- body -->
    <g>
      <rect x="130" y="512" width="22" height="34" rx="8" fill="#ff8c42"/>
      <!-- green trousers -->
      <rect x="132" y="540" width="8" height="16" rx="3" fill="#4a7c59"/>
      <rect x="143" y="540" width="8" height="16" rx="3" fill="#4a7c59"/>
      <!-- boots -->
      <path d="M128 560 v-5 q0 -2 2 -2 h6 q2 0 2 2 v5 z" fill="#e3d3b5"/>
      <path d="M139 560 v-5 q0 -2 2 -2 h6 q2 0 2 2 v5 z" fill="#e3d3b5"/>
      <circle cx="141" cy="500" r="11" fill="#f0b48f"/>
      <path d="M128 498 a13 13 0 0 1 26 0 z" fill="#ffc93c"/>
      <rect x="126" y="496" width="30" height="4" rx="2" fill="#e0a92e"/>
    </g>
    <!-- arm and pickaxe on one axis -->
    <g class="arm">
      <line x1="140" y1="511" x2="105" y2="469" stroke="#8a5a2b" stroke-width="5"/>
      <path d="M92 480 Q 97 460 118 458" stroke="#9aa7b8" stroke-width="7" fill="none"/>
      <line x1="146" y1="518" x2="125" y2="493" stroke="#e0731f" stroke-width="6" stroke-linecap="round"/>
      <circle cx="125" cy="493" r="4" fill="#f0b48f"/>
    </g>
    <g class="spark">
      <circle cx="86" cy="522" r="3" fill="#ffd97a"/>
      <circle cx="79" cy="516" r="2" fill="#ffd97a"/>
    </g>
  </g>

  <!-- traffic cones -->
  <g>
    <path d="M950 560 l10 -30 l10 30 z" fill="#ff7a4e"/>
    <rect x="946" y="556" width="28" height="6" rx="2" fill="#ff9b78"/>
    <rect x="955" y="542" width="10" height="5" fill="#fff"/>
    <path d="M905 560 l9 -26 l9 26 z" fill="#ff7a4e"/>
    <rect x="901" y="556" width="26" height="6" rx="2" fill="#ff9b78"/>
    <rect x="909" y="546" width="10" height="4" fill="#fff"/>
  </g>

</svg>
</div>

<h1>Under <span>Construction</span></h1>
<p class="sub">We're working on something great, coming soon<span class="dots"><span></span><span></span><span></span></span></p>

<div class="notify">
<?php if ($notifySaved): ?>
  <p class="notify-msg ok">Thank you! We'll let you know as soon as the site is live.</p>
<?php else: ?>
  <button type="button" class="notify-btn" id="notifyBtn">Notify me when it's ready</button>
  <form id="notifyForm" method="post" action="" hidden>
    <input type="hidden" name="ts" value="<?php echo htmlspecialchars($tsToken, ENT_QUOTES); ?>">
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true"
           style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
    <input type="email" name="email" placeholder="your@email.com" required maxlength="254"
           value="<?php echo $notifyError ? htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) : ''; ?>">
    <button type="submit" class="notify-btn">Notify me</button>
  </form>
  <?php if ($notifyError): ?>
    <p class="notify-msg err"><?php echo htmlspecialchars($notifyErrorMsg, ENT_QUOTES); ?></p>
  <?php endif; ?>
<?php endif; ?>
</div>

<script>
  var btn = document.getElementById('notifyBtn');
  var form = document.getElementById('notifyForm');
  if (btn && form) {
    <?php if ($notifyError): ?>
    btn.hidden = true;
    form.hidden = false;
    <?php endif; ?>
    btn.addEventListener('click', function () {
      btn.hidden = true;
      form.hidden = false;
      form.querySelector('input[type="email"]').focus();
    });
  }
</script>
</body>
</html>
