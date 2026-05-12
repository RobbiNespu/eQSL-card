<?php $this->extend('/Help/view'); ?>
<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'This guide is on the way.',
]) ?>
<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => "We haven't written this article yet. In the meantime, the Welcome guide covers the basics. Have a specific question? Reach the operator on the homepage.",
]) ?>
