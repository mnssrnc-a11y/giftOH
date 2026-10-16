{{--
    One published update in the admin's Updates section, with the choice to edit or delete it.
    "Edit" opens its form (the template below) in the admin dialog; "Delete" asks first.
    @param array    $post       AdminDashboardService post row
    @param array    $postTypes  type => label
    @param \Closure $when       date → "2 days ago"
--}}
<article class="admin-post">
    <div class="admin-post-head">
        <div>
            <strong>{{ $post['title'] }}</strong>
            <small>{{ $post['author'] }} · {{ $when($post['date']) }}@if ($post['edited_at']) · edited {{ $when($post['edited_at']) }} by {{ $post['editor'] }}@endif</small>
        </div>
        <div class="admin-actions">
            <span class="admin-status {{ $post['audience'] === 'public' ? 'approved' : 'cancelled' }}">{{ $post['audience'] === 'public' ? 'Public' : 'Users only' }}</span>
            <span class="admin-status in-progress">{{ $postTypes[$post['type']] ?? 'Update' }}</span>
        </div>
    </div>
    @if ($post['image'])<img class="admin-post-image" src="{{ $post['image'] }}" alt="Photo for {{ $post['title'] }}" loading="lazy">@endif
    <p>{{ $post['body'] }}</p>
    <div class="admin-actions admin-post-actions">
        <button type="button" class="admin-button" data-edit-post="{{ $post['id'] }}" aria-label="Edit “{{ $post['title'] }}”">✎ Edit</button>
        <form method="POST" action="{{ route('admin.posts.destroy', $post['id']) }}" data-delete-post>
            @csrf @method('DELETE')
            <button class="admin-button danger" type="submit" aria-label="Delete “{{ $post['title'] }}”">Delete</button>
        </form>
    </div>
    <template data-edit-post-form="{{ $post['id'] }}">
        <h2 id="adminModalTitle">Edit update</h2>
        <form class="admin-post-form" method="POST" action="{{ route('admin.posts.update', $post['id']) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="admin-field"><label for="edit-post-type">Type</label><select id="edit-post-type" class="admin-select" name="type" style="width:100%">@foreach ($postTypes as $value => $label)<option value="{{ $value }}" @selected($post['type'] === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="admin-field"><label for="edit-post-audience">Who can see it</label><select id="edit-post-audience" class="admin-select" name="audience" style="width:100%"><option value="public" @selected($post['audience'] === 'public')>Everyone: landing page and user pages</option><option value="users" @selected($post['audience'] === 'users')>Signed-in users only</option></select></div>
            <div class="admin-field"><label for="edit-post-title">Title</label><input id="edit-post-title" class="admin-input" name="title" placeholder="e.g. Rice drive for Promised Land families" maxlength="150" required value="{{ $post['title'] }}"></div>
            <div class="admin-field"><label for="edit-post-body">Message</label><textarea id="edit-post-body" class="admin-textarea" name="body" placeholder="e.g. Thank you to everyone who donated! This week we delivered 40 sacks of rice to…" rows="8" maxlength="10000" required>{{ $post['body'] }}</textarea></div>
            <div class="admin-field">
                <label for="edit-post-image">{{ $post['image'] ? 'Replace the photo' : 'Add a photo' }} <span style="font-weight:400">(optional)</span></label>
                @if ($post['image'])<img class="admin-post-image" src="{{ $post['image'] }}" alt="Current photo" data-edit-image-preview>@else<img class="admin-post-image" alt="Selected photo" data-edit-image-preview hidden>@endif
                <input id="edit-post-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" class="admin-input" style="width:100%;height:auto;padding:9px" data-edit-image>
                <p class="admin-field-hint">JPG, PNG or WebP, up to 5 MB.</p>
                @if ($post['image'])<label class="admin-check"><input type="checkbox" name="remove_image" value="1" data-edit-remove-image> Remove the photo</label>@endif
            </div>
            <div class="admin-modal-actions"><button type="button" class="admin-button" data-modal-close>Cancel</button><button class="admin-button primary" type="submit">Save changes</button></div>
        </form>
    </template>
</article>
