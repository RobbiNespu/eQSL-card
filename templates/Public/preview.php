<h1>Your eQSL is ready</h1>
<p>Download the image or PDF below. Refresh the page and it'll be gone — create a free account if you want to keep your cards.</p>

<img class="card-preview" src="<?= h($pngUrl) ?>" alt="Generated eQSL card">

<div class="d-flex gap-2 mt-4 flex-wrap">
  <a class="btn btn-primary" href="<?= h($pngUrl) ?>" download>Download image</a>
  <a class="btn btn-secondary" href="<?= h($pdfUrl) ?>">Download PDF</a>
  <a class="btn btn-link" href="/">Generate another</a>
</div>

<p class="form-text mt-4">
  <a href="/register">Create a free account</a> to save this card to your library and skip re-entering your callsign next time.
</p>
