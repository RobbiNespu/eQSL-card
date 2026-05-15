<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'Design your own template — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to create and edit eQSL card templates in the Fabric.js visual designer.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'The visual designer lets you place QSO data fields, free-text labels, and decorative elements on a canvas — then save the layout as a reusable template.',
]) ?>

<h2>Opening the designer</h2>
<p>Go to <a href="/templates">/templates</a> and click <strong>New template</strong>, or click <strong>Edit</strong> on any of your own personal templates. You can also <strong>Clone</strong> a system or public template first — that gives you a copy you can freely modify.</p>

<h2>The canvas</h2>
<p>The designer canvas is a 1500×1000 pixel workspace (default dimensions; can be changed in the settings panel on the right). It represents the card's final proportions. The actual rendered image is composited by the server at the same resolution — what you see in the designer is what you get on the card.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/templates/designer/canvas.webp',
    'alt' => 'The designer canvas with a sample background, two callsign fields, and a date field placed',
    'caption' => 'The canvas shows a live preview of the template layout.',
]) ?>

<h2>Adding elements</h2>
<p>Use the toolbar on the left to add elements to the canvas:</p>
<ul>
  <li><strong>Data field</strong> — a placeholder bound to a QSO attribute. At render time, the server substitutes the real value. Available fields: <em>your callsign</em>, <em>their callsign</em>, <em>date UTC</em>, <em>time UTC</em>, <em>band</em>, <em>mode</em>, <em>frequency</em>, <em>RST sent / received</em>, <em>their name</em>, <em>their QTH</em>, <em>grid square</em>, <em>NCS callsign</em>, <em>net title</em>, <em>organisation</em>.</li>
  <li><strong>Free text</strong> — static text that appears on every card: headings, labels, station info. e.g. "Confirming contact with:".</li>
  <li><strong>Horizontal rule</strong> — a thin decorative line.</li>
</ul>

<h2>Selecting and moving elements</h2>
<p>Click any element on the canvas to select it. Drag to reposition. Use the corner handles to resize. Hold Shift while clicking to multi-select; drag the group to move several elements together. Delete key removes the selected element.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/templates/designer/element-selected.webp',
    'alt' => 'A callsign data field selected on the canvas with resize handles visible',
    'caption' => 'Select an element to see its properties panel on the right.',
]) ?>

<h2>The properties panel</h2>
<p>When an element is selected, the right-hand panel shows its properties:</p>
<ul>
  <li><strong>Font family</strong> — choose from the bundled fonts (Inter, Geist Mono, etc.).</li>
  <li><strong>Font size</strong> — in points relative to the canvas.</li>
  <li><strong>Font weight / style</strong> — bold, italic, or both.</li>
  <li><strong>Colour</strong> — text colour with a hex/colour picker.</li>
  <li><strong>Alignment</strong> — left, centre, right within the element's bounding box.</li>
  <li><strong>Opacity</strong> — useful for watermark-style overlays.</li>
</ul>

<h2>Canvas settings</h2>
<p>Click the <strong>Settings</strong> tab at the top of the right panel to change the template's name, description, and canvas dimensions. Changing dimensions after you've placed elements will reposition them proportionally.</p>

<?= $this->element('ui/callout', [
    'variant' => 'warning',
    'body' => 'The background image shown in the designer is a placeholder — the actual background is chosen by the operator at render time, not stored in the template. Use a representative placeholder to judge contrast and readability, but know that users can swap it for any uploaded image.',
]) ?>

<h2>Saving</h2>
<p>Click <strong>Save</strong>. The designer sends the layout JSON to the server, which stores it and generates a thumbnail using the bundled demo background. The save dialog also has a <strong>Make this template public</strong> checkbox — tick it if you want to submit the template for community use. See <a href="/help/templates/submit-public">Submit a template to the gallery</a>.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/templates/designer/save-dialog.webp',
    'alt' => 'Save dialog with template name, description, and "Make public" checkbox',
    'caption' => 'The save dialog lets you name and optionally submit for review.',
]) ?>

<h2>Keyboard shortcuts</h2>
<ul>
  <li><kbd>Delete</kbd> / <kbd>Backspace</kbd> — remove selected element.</li>
  <li><kbd>Ctrl+Z</kbd> — undo last canvas change.</li>
  <li><kbd>Ctrl+Y</kbd> — redo.</li>
  <li><kbd>Ctrl+A</kbd> — select all elements.</li>
  <li><kbd>Esc</kbd> — deselect.</li>
</ul>
