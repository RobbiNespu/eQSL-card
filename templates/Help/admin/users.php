<?php $this->extend('/Help/view'); ?>
<?php $this->assign('title', 'User management — eQSL Card Help'); ?>
<?php $this->start('meta'); ?>
<meta name="description" content="How to search, promote, and soft-delete users from the eQSL Card admin panel.">
<?php $this->end(); ?>

<?= $this->element('ui/page_header', [
    'title' => $title,
    'lede'  => 'The user management page at /admin/users lets you search registered users, change roles, and soft-delete accounts.',
]) ?>

<h2>Finding users</h2>
<p>Visit <a href="/admin/users">/admin/users</a>. The table lists all non-deleted accounts, newest first, paginated at 30 per page. Use the search box at the top to filter by email address, callsign, or name — the match is a substring check so a partial callsign like <code>9W2</code> or a partial email like <code>@gmail</code> works.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/users/user-list.webp',
    'alt' => 'The admin users table showing callsign, name, email, role, and joined date columns, with a search box',
    'caption' => 'The user list with search. The Role column shows the current role badge.',
]) ?>

<h2>Roles</h2>
<p>There are two roles:</p>
<ul>
  <li><strong>user</strong> — can log QSOs, render cards, manage their own templates and library. No access to <code>/admin/*</code>.</li>
  <li><strong>admin</strong> — full access to all admin pages. Can promote other users to admin, delete accounts, review templates, run cleanup, and change global settings.</li>
</ul>

<h2>Changing a user's role</h2>
<p>Click <strong>Edit</strong> on any row. The edit form shows the current role and a dropdown to change it. Save to apply.</p>

<?= $this->element('ui/callout', [
    'variant' => 'warning',
    'body' => 'You cannot demote your own account. Attempting to do so shows an error. This prevents an admin from accidentally locking themselves out. To demote your own account, ask another admin to do it.',
]) ?>

<h2>Soft-deleting a user</h2>
<p>Click <strong>Delete</strong> on any row (not your own). The account is soft-deleted — the <code>deleted_at</code> column is stamped and the user no longer appears in the list or can sign in. Their QSOs, cards, and uploads are not deleted; if you need to remove those, use the <a href="/admin/cleanup">storage cleanup</a> tools.</p>

<?= $this->element('ui/callout', [
    'variant' => 'note',
    'body' => 'Soft-delete is permanent from the admin UI — there\'s no "restore" button. If you need to reactivate an account, you\'ll need to clear the <code>deleted_at</code> value directly in the database.',
]) ?>

<h2>Creating new accounts</h2>
<p>eQSL Card uses self-registration — users sign up via <a href="/register">/register</a>. There's no admin-side "create user" form. To create an admin account, have the person register normally, then promote them here.</p>

<?= $this->element('ui/screenshot', [
    'src' => '/files/help/admin/users/edit-role.webp',
    'alt' => 'The edit-user form showing a role dropdown set to "admin"',
    'caption' => 'Promoting a user to admin is a single dropdown change.',
]) ?>

<h2>Audit trail</h2>
<p>Every role change and soft-delete is written to the <a href="/admin/audit">audit log</a> with the acting admin's user ID and the old and new values, so there's always a record of who changed what.</p>
