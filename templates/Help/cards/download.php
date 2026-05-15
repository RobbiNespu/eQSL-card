<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Download as image or PDF — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to download your eQSL card as a WebP image or a PDF file.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'Every rendered card can be downloaded as a high-quality image or wrapped in a PDF for printing or archiving.',
]) ?>

<h2>From your card library</h2>
<p>Open any card from your <a href="/cards">library</a> by clicking its thumbnail. The card detail page has two download buttons under the full-size image:</p>
<ul>
  <li><strong>Download image</strong> — downloads the stored WebP (or PNG for older cards) directly from the server. No server-side processing needed; it's the exact file produced at render time.</li>
  <li><strong>Download PDF</strong> — wraps the image in a PDF envelope on the fly and streams it to your browser. The PDF uses the template's declared canvas dimensions (e.g. A5 landscape at 150 dpi) so it prints at the right size without cropping.</li>
</ul>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/cards/download/download-buttons.webp',
    'alt' => 'Card detail page showing the "Download image" and "Download PDF" buttons below the card',
    'caption' => 'Download image for digital use, PDF for printing.',
]) ?>

<h2>From a share link (no account needed)</h2>
<p>Recipients who open a share link (<code>/qsl/{slug}</code>) also see download buttons. They can save the image or PDF without signing up for an account. The PDF button on the share page works identically — it wraps the same image in a PDF on the fly and streams it.</p>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'The PDF is generated fresh on every download request. It is not cached on disk — only the image is persisted. On very high-traffic shared links this means each PDF download costs a small rendering step, but for typical amateur-radio usage this is unnoticeable.',
]) ?>

<h2>Image format and quality</h2>
<p>Cards rendered after the M2 storage-saver update are stored as <strong>WebP</strong> at quality 86, targeting a 2000×1500 px canvas (or whatever the template declares). WebP gives roughly 30% smaller files than JPEG at equivalent quality, which matters when 50 cards sit in <code>webroot/files/cards/</code>. Cards rendered before that update may be PNG — the download button serves whichever format is on disk.</p>

<h2>Using the image elsewhere</h2>
<p>WebP is supported by every modern browser and most social platforms. If a recipient's app needs JPEG, they can convert with any image editor or CLI tool (<code>cwebp</code>, ImageMagick, GIMP, etc.). The file name the server suggests is <code>card-{id}.webp</code> (or <code>.pdf</code>) — rename it before uploading anywhere.</p>

<?= $this->element('ui/callout', [
    'variant' => 'tip',
    'body' => 'For email, the image download is the right choice — attach the WebP or convert it to JPEG. PDF is better for printing: open in any PDF viewer and print at "actual size" or 100% scale to get the template\'s intended dimensions on paper.',
]) ?>
