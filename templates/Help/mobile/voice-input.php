<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Voice input on the callsign field — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="An opt-in microphone button next to the quick-add callsign input. Tap, say the callsign in NATO phonetic, and the decoded characters land in the input — uses the Web Speech API on supported browsers.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'An opt-in microphone button on the quick-add callsign field. Tap it, say the callsign in NATO phonetic ("nine mike two romeo delta x-ray"), and the decoded characters land in the input. Designed for the moment your fingers are busy holding the radio.',
]) ?>

<h2>Why this exists</h2>
<p>During a busy net or contest, the bottleneck on quick-add is typing the callsign. You hear it on the radio, look down at the phone, type six or seven characters, look back at the radio, then type the rest. Two context switches per contact. Voice input collapses this: tap once, say the callsign the same way you'd repeat it to the operator, glance to confirm the decode, tap Save.</p>

<p>It's also accessibility-relevant — for operators with hand mobility limitations, typing on a phone keyboard during a pile-up is impractical. Voice replaces the typing step entirely.</p>

<h2>Enabling it</h2>
<p>The mic button is hidden by default. Two conditions must be met for it to render:</p>

<ol>
  <li><strong>Profile preference is ON.</strong> Go to <a href="/profile">your profile page</a> and turn on <em>"Show a microphone button on the Quick-add callsign field"</em> under "Quick-add helpers".</li>
  <li><strong>The browser exposes the Web Speech API.</strong> See <a href="#browser-support">Browser support</a> below — Firefox doesn't, for instance, and the button stays hidden there even with the preference on.</li>
</ol>

<p>If both conditions are met, the mic button appears next to the callsign input. If either is false, the input looks exactly as it does without the feature.</p>

<h2>How to use it</h2>
<ol>
  <li>Tap the <strong>🎤</strong> button. The button turns red, the icon changes to a record-style indicator, and a status line under the input reads <em>"Listening… say the callsign."</em></li>
  <li>Speak the callsign. NATO/ITU phonetic is the safest form, but the decoder accepts several variants (see <a href="#what-gets-decoded">What gets decoded</a>).</li>
  <li>Stop speaking. The recogniser returns within a second or two, the input fills with the decoded callsign, and the status line briefly shows what it heard: <em>"Heard 'nine mike two romeo delta x-ray' → 9M2RDX"</em>.</li>
  <li>Glance to verify the decode is right. Tap Save (or hit the keyboard's enter key).</li>
</ol>

<p>If you change your mind mid-utterance, tap the mic again to abort. The status line shows <em>"Cancelled."</em> and the input stays unchanged.</p>

<h2 id="what-gets-decoded">What gets decoded</h2>
<p>The decoder is forgiving. It accepts:</p>

<ul>
  <li><strong>Standard NATO/ITU words</strong> — <code>alpha bravo charlie delta echo foxtrot golf hotel india juliet kilo lima mike november oscar papa quebec romeo sierra tango uniform victor whiskey x-ray yankee zulu</code>; <code>zero one two three four five six seven eight nine</code>.</li>
  <li><strong>ITU variants</strong> — <code>alfa</code> (for alpha), <code>juliett</code> (double-t), <code>whisky</code> (no e).</li>
  <li><strong>Military / maritime variants</strong> — <code>niner</code> (9), <code>tree</code> (3), <code>fife</code> (5), <code>fower</code> (4). The recogniser often returns these from veteran operators.</li>
  <li><strong>X-ray variants</strong> — <code>x-ray</code>, <code>x ray</code>, <code>xray</code>, or <code>exray</code> all decode to X.</li>
  <li><strong>Filler words</strong> — <code>this is</code>, <code>over</code>, <code>out</code>, <code>calling cq</code>, <code>de</code>, the articles (<code>a</code>, <code>an</code>, <code>the</code>) — silently dropped. So <em>"this is whiskey one alpha whiskey over"</em> decodes to <code>W1AW</code>.</li>
  <li><strong>Glued chunks</strong> — if the recogniser returns "9m2rdx" as a single alphanumeric token instead of spelling it out phonetically, that passes through as <code>9M2RDX</code>. Some Android speech engines do this when the speaker says the callsign quickly.</li>
</ul>

<p>Two things deliberately <em>not</em> mapped: the English words "for" and "to" are dropped rather than turned into 4 and 2. They're far too common in everyday speech to safely treat as digits — say "four" or "fower" if you mean the digit.</p>

<p>Anything the decoder doesn't recognise gets dropped. The resulting input is the concatenation of every recognised letter/digit in the order spoken. If you speak slowly and clearly, the decode is usually exact. If you mumble or speak over background QRM, the recogniser itself returns garbled words and the decode comes out short or wrong.</p>

<h3>If the decode is wrong</h3>
<p>Two paths:</p>
<ol>
  <li>Tap the mic again and repeat the callsign. The new attempt replaces the previous decode in the input.</li>
  <li>Tap into the input and fix the wrong letters by hand. The mic only fills the input; you stay in full control of its contents.</li>
</ol>

<h2 id="browser-support">Browser support</h2>
<p>Voice input rides on the <a href="https://developer.mozilla.org/en-US/docs/Web/API/Web_Speech_API" rel="noopener">Web Speech API</a>. Browser coverage is patchy, which is why the feature is opt-in.</p>

<table>
  <thead><tr><th>Browser</th><th>Support</th><th>Notes</th></tr></thead>
  <tbody>
    <tr>
      <td><strong>Chrome / Edge (Android)</strong></td>
      <td>✅ Works</td>
      <td>Recognition routes through Google's cloud — your speech is sent off-device for transcription. If that's a privacy concern, leave the preference off.</td>
    </tr>
    <tr>
      <td><strong>Chrome / Edge (desktop)</strong></td>
      <td>✅ Works</td>
      <td>Same Google-cloud routing. Microphone permission requested on first use, remembered per-site.</td>
    </tr>
    <tr>
      <td><strong>Safari (iOS 16+)</strong></td>
      <td>⚠️ Partial</td>
      <td>Recognition uses on-device transcription on most newer iPhones (no cloud). Requires explicit microphone permission per page — Safari does not remember it across sessions in all cases.</td>
    </tr>
    <tr>
      <td><strong>Safari (macOS 13+)</strong></td>
      <td>✅ Works</td>
      <td>Similar to iOS 16. The "🎤" key on the Mac's keyboard does <em>not</em> trigger Web Speech — only the in-page button does.</td>
    </tr>
    <tr>
      <td><strong>Firefox (any platform)</strong></td>
      <td>❌ No support</td>
      <td>The Web Speech API isn't implemented. The mic button stays hidden even with the preference on. No timeline from Mozilla.</td>
    </tr>
    <tr>
      <td><strong>Brave / DuckDuckGo / privacy browsers</strong></td>
      <td>⚠️ Varies</td>
      <td>Most are Chromium-based, so the API is present — but the cloud-recognition routing may be blocked by the privacy shield. Toggle shields off on the site if voice fails to return.</td>
    </tr>
  </tbody>
</table>

<h2>Privacy</h2>
<p>The app itself never receives your audio — it only receives the transcribed text from the browser's speech recogniser. On Android and desktop Chrome / Edge, that recogniser is Google's cloud service; on iOS 16+ and macOS 13+ Safari, it's an on-device model.</p>

<ul>
  <li><strong>The site does not record audio.</strong> No <code>MediaRecorder</code>, no upload, no server-side audio file.</li>
  <li><strong>The transcribed text reaches our server only when you tap Save.</strong> It goes in as the callsign field, identical to typing.</li>
  <li><strong>You can verify this</strong> — open DevTools → Network and use the mic. You'll see only the dupe-check request fire (text query string), not any audio upload.</li>
</ul>

<p>If you want to be sure no cloud transcription happens, use iOS 16+ Safari (on-device only). If you're not comfortable with cloud transcription at all, keep the preference off — typing six characters takes a second longer.</p>

<h2>Limits</h2>
<ul>
  <li><strong>English only.</strong> The recogniser is configured with <code>en-US</code> as the language hint. Non-English NATO equivalents (e.g. German "Bertha" for B) don't decode. If you need a different language hint, an additional preference may be added in a later release.</li>
  <li><strong>Single-shot recognition.</strong> The mic button does one utterance per tap — no continuous listening (that drains battery and raises a real privacy concern).</li>
  <li><strong>No suffix recognition.</strong> Some operators say "stroke portable" or "slash mobile" at the end of a callsign. The decoder accepts "slash" → <code>/</code> and "stroke" → <code>/</code>, but the actual suffix word ("portable" / "mobile" / "maritime") is dropped — type those by hand if you need them.</li>
  <li><strong>Background noise degrades the recogniser, not the decoder.</strong> If the recogniser returns gibberish words, the decoder has nothing useful to map. Pile-ups, generator noise, helicopter overhead — all problems for accuracy. Stick with typing in those environments.</li>
</ul>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'During a busy net, the fastest pattern is: tap mic → say "this is [callsign] over" → glance at the input → tap Save. The filler-word dropper means you don\'t have to chop your phrasing — the operator-feel is the same as calling on the radio.',
]) ?>
