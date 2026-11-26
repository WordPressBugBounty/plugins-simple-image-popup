<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/*
 * Translations come from WordPress (wp-i18n). Every string is a literal
 * `i18n.__( '…', 'simple-image-popup' )` style call so WP-CLI / translate.wordpress.org
 * can extract it from the built bundle: keep the text domain literal and never wrap
 * these calls in a helper. Minification renames `i18n` but keeps `.__` / `._n` / `._x`.
 */
const fallbackSprintf = (format, ...args) => {
  let index = 0;
  return String(format).replace(/%(?:(\d+)\$)?[sd]/g, (match, position) =>
    String(args[position ? Number(position) - 1 : index++] ?? ''),
  );
};
const i18n = window.wp?.i18n ?? {
  __: (text) => text,
  _x: (text) => text,
  _n: (single, plural, number) => (number === 1 ? single : plural),
  sprintf: fallbackSprintf,
};
const { sprintf } = i18n;

const config = window.SimpleImagePopupAdmin || {};
const clonePopup = (popup) => JSON.parse(JSON.stringify(popup));
const snapshotOf = (popup) => JSON.stringify(popup);
const defaultPopup = () => ({
  id: 0,
  title: '',
  status: 'draft',
  enabled: false,
  imageId: 0,
  imageUrl: '',
  linkUrl: '',
  maxWidth: 700,
  delaySeconds: 1,
  repeatMinutes: 30,
  clickToClose: false,
  displayKey: '',
  targetMode: 'all',
  targetPostIds: [],
  targetPosts: [],
  views: 0,
  isMigrated: false,
});

const popups = ref(Array.isArray(config.initialPopups) ? config.initialPopups : []);
const activeId = ref(popups.value[0]?.id ?? 0);
const form = ref(popups.value[0] ? clonePopup(popups.value[0]) : defaultPopup());
const savedSnapshot = ref(snapshotOf(form.value));
const searchQuery = ref('');
const searchResults = ref([]);
const hasSearched = ref(false);
const isLoadingResults = ref(false);
const isSaving = ref(false);
const isDeleting = ref(false);
const notice = ref('');
const errorMessage = ref('');
const errorIsValidation = ref(false);
const fieldErrors = ref({});
const imageFailed = ref(false);
const editorHeading = ref(null);

const pageTitle = config.pageTitle || i18n.__('Simple Image Popup', 'simple-image-popup');
const typeLabels = {
  post: i18n._x('Post', 'content type', 'simple-image-popup'),
  page: i18n._x('Page', 'content type', 'simple-image-popup'),
};

const isDirty = computed(() => snapshotOf(form.value) !== savedSnapshot.value);
const currentTitle = computed(
  () =>
    form.value.title.trim() ||
    (form.value.id ? i18n.__('Untitled popup', 'simple-image-popup') : i18n.__('New popup', 'simple-image-popup')),
);

/**
 * A popup only reaches visitors when it is published, enabled, has an image
 * and (for selected targeting) has at least one page. Say which one is missing.
 */
function getVisibility(popup) {
  if (!popup.imageUrl) {
    return {
      tone: 'warning',
      short: i18n.__('Needs image', 'simple-image-popup'),
      label: i18n.__('Not showing: add an image', 'simple-image-popup'),
    };
  }
  if (popup.status !== 'publish') {
    return {
      tone: 'off',
      short: i18n.__('Draft', 'simple-image-popup'),
      label: i18n.__('Draft: not showing on your site', 'simple-image-popup'),
    };
  }
  if (!popup.enabled) {
    return {
      tone: 'off',
      short: i18n.__('Paused', 'simple-image-popup'),
      label: i18n.__('Paused: turned off', 'simple-image-popup'),
    };
  }
  if (popup.targetMode === 'selected' && popup.targetPostIds.length === 0) {
    return {
      tone: 'warning',
      short: i18n.__('No pages', 'simple-image-popup'),
      label: i18n.__('Not showing: no pages chosen', 'simple-image-popup'),
    };
  }
  const live = i18n.__('Live', 'simple-image-popup');
  if (popup.targetMode === 'selected') {
    const count = popup.targetPostIds.length;
    return {
      tone: 'live',
      short: live,
      label: sprintf(/* translators: %d: number of posts and pages the popup is shown on. */ i18n._n('Live on %d page', 'Live on %d pages', count, 'simple-image-popup'), count),
    };
  }
  return { tone: 'live', short: live, label: i18n.__('Live on every page', 'simple-image-popup') };
}

const visibility = computed(() => getVisibility(form.value));

function formatViews(count) {
  const value = Number(count) || 0;
  return sprintf(/* translators: %s: formatted number of times the popup was shown. */ i18n._n('%s view', '%s views', value, 'simple-image-popup'), value.toLocaleString());
}

function formatViewsSoFar(count) {
  const value = Number(count) || 0;
  return sprintf(/* translators: %s: formatted number of times the popup was shown. */ i18n._n('%s view so far', '%s views so far', value, 'simple-image-popup'), value.toLocaleString());
}

function formatDurationPart(amount, unit) {
  if (unit === 'day') {
    return sprintf(/* translators: %d: number of days. */ i18n._n('%d day', '%d days', amount, 'simple-image-popup'), amount);
  }
  if (unit === 'hour') {
    return sprintf(/* translators: %d: number of hours. */ i18n._n('%d hour', '%d hours', amount, 'simple-image-popup'), amount);
  }
  return sprintf(/* translators: %d: number of minutes. */ i18n._n('%d minute', '%d minutes', amount, 'simple-image-popup'), amount);
}

function formatMinutes(total) {
  const minutes = Number(total);
  if (!Number.isFinite(minutes) || minutes <= 0) {
    return i18n.__('Shows on every page view.', 'simple-image-popup');
  }
  const units = [
    [1440, 'day'],
    [60, 'hour'],
    [1, 'minute'],
  ];
  const parts = [];
  let remaining = Math.floor(minutes);
  for (const [size, unit] of units) {
    const amount = Math.floor(remaining / size);
    if (amount > 0) {
      parts.push(formatDurationPart(amount, unit));
      remaining -= amount * size;
    }
    if (parts.length === 2) {
      break;
    }
  }
  const duration =
    parts.length === 2
      ? sprintf(/* translators: Two parts of a duration, e.g. "1 day" and "3 hours". */ i18n._x('%1$s %2$s', 'duration', 'simple-image-popup'), parts[0], parts[1])
      : parts[0];
  return sprintf(/* translators: %s: a duration such as "1 day 3 hours". */ i18n.__('Visitors see it again after %s.', 'simple-image-popup'), duration);
}

const repeatHelp = computed(() => formatMinutes(form.value.repeatMinutes));

const searchStatus = computed(() => {
  if (isLoadingResults.value) {
    return i18n.__('Searching…', 'simple-image-popup');
  }
  if (!hasSearched.value) {
    return '';
  }
  const count = searchResults.value.length;
  if (count === 0) {
    return i18n.__('No posts or pages match that search.', 'simple-image-popup');
  }
  return sprintf(/* translators: %d: number of search results. */ i18n._n('%d result.', '%d results.', count, 'simple-image-popup'), count);
});

const targetsHeading = computed(() => {
  const count = form.value.targetPosts.length;
  return sprintf(/* translators: %d: number of posts and pages chosen for the popup. */ i18n._n('Showing on %d page', 'Showing on %d pages', count, 'simple-image-popup'), count);
});

function titleOf(post) {
  return post.title || i18n.__('(no title)', 'simple-image-popup');
}

function addLabel(post) {
  return isTargeted(post.id)
    ? sprintf(/* translators: %s: post or page title. */ i18n.__('%s added', 'simple-image-popup'), titleOf(post))
    : sprintf(/* translators: %s: post or page title. */ i18n.__('Add %s', 'simple-image-popup'), titleOf(post));
}

function removeLabel(post) {
  return sprintf(/* translators: %s: post or page title. */ i18n.__('Remove %s', 'simple-image-popup'), titleOf(post));
}

function describedBy(...ids) {
  return ids.filter(Boolean).join(' ') || undefined;
}

function errorId(field) {
  return fieldErrors.value[field] ? `sip-error-${field}` : '';
}

function showError(message, isValidation = false) {
  errorMessage.value = message;
  errorIsValidation.value = isValidation;
}

function resetEditorState() {
  savedSnapshot.value = snapshotOf(form.value);
  showError('');
  fieldErrors.value = {};
  imageFailed.value = false;
  searchQuery.value = '';
  searchResults.value = [];
  hasSearched.value = false;
}

function confirmDiscard() {
  if (!isDirty.value) {
    return true;
  }
  return window.confirm(
    sprintf(/* translators: %s: popup title. */ i18n.__('You have unsaved changes to “%s”. Discard them?', 'simple-image-popup'), currentTitle.value),
  );
}

function loadPopup(popup) {
  activeId.value = popup ? popup.id : 0;
  form.value = popup ? clonePopup(popup) : defaultPopup();
  resetEditorState();
}

function selectPopup(popup) {
  if (popup.id === activeId.value || !confirmDiscard()) {
    return;
  }
  notice.value = '';
  loadPopup(popup);
  // Start the newly opened popup from its header rather than mid-form.
  const editor = document.querySelector('.sip-admin__layout');
  if (editor && editor.getBoundingClientRect().top < 0) {
    editor.scrollIntoView({ block: 'start' });
  }
}

async function createPopup() {
  if (!confirmDiscard()) {
    return;
  }
  notice.value = '';
  loadPopup(null);
  await nextTick();
  document.getElementById('sip-title')?.focus();
}

function getHeaders() {
  return {
    'Content-Type': 'application/json',
    'X-WP-Nonce': config.nonce,
  };
}

async function request(path, options = {}, query = {}) {
  const url = new URL(config.apiBase, window.location.href);
  if (url.searchParams.has('rest_route')) {
    url.searchParams.set('rest_route', `${url.searchParams.get('rest_route').replace(/\/$/, '')}${path}`);
  } else {
    url.pathname = `${url.pathname.replace(/\/$/, '')}${path}`;
  }
  for (const [name, value] of Object.entries(query)) {
    url.searchParams.set(name, value);
  }

  const response = await fetch(url, {
    headers: getHeaders(),
    ...options,
  });

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(payload.message || i18n.__('Something went wrong. Please try again.', 'simple-image-popup'));
  }

  return payload;
}

function isHttpUrl(value) {
  if (/\s/.test(value)) {
    return false;
  }
  try {
    // Resolve against the admin URL so site-relative paths (e.g. /wp-content/…) still count, as they do on the server.
    const url = new URL(value, window.location.href);
    return url.protocol === 'http:' || url.protocol === 'https:';
  } catch {
    return false;
  }
}

/**
 * Mirror the server rules so problems show next to the field before saving.
 * Returns the id of the first control that needs attention.
 */
function validate() {
  const errors = {};
  const popup = form.value;
  let firstInvalid = '';
  const flag = (field, message, controlId) => {
    errors[field] = message;
    firstInvalid ||= controlId;
  };

  if (!popup.title.trim()) {
    flag('title', i18n.__('Add a title.', 'simple-image-popup'), 'sip-title');
  }
  if (!popup.imageUrl.trim()) {
    flag('image', i18n.__('Choose an image or paste an image URL.', 'simple-image-popup'), 'sip-choose-image');
  } else if (!popup.imageId && !isHttpUrl(popup.imageUrl.trim())) {
    flag(
      'image',
      i18n.__('Enter a valid image URL, such as https://example.com/popup.jpg', 'simple-image-popup'),
      'sip-image-url',
    );
  }
  if (popup.linkUrl.trim() && !isHttpUrl(popup.linkUrl.trim())) {
    flag(
      'linkUrl',
      i18n.__('Enter a valid web address, such as https://example.com/offer', 'simple-image-popup'),
      'sip-link-url',
    );
  }
  if (!Number.isFinite(popup.delaySeconds) || popup.delaySeconds < 0) {
    flag('delaySeconds', i18n.__('Use 0 or more seconds.', 'simple-image-popup'), 'sip-delay');
  }
  if (!Number.isFinite(popup.repeatMinutes) || popup.repeatMinutes < 0) {
    flag('repeatMinutes', i18n.__('Use 0 or more minutes.', 'simple-image-popup'), 'sip-repeat');
  }
  if (!Number.isFinite(popup.maxWidth) || popup.maxWidth < 200) {
    flag('maxWidth', i18n.__('Use a width of 200 pixels or more.', 'simple-image-popup'), 'sip-max-width');
  }

  fieldErrors.value = errors;
  return firstInvalid;
}

async function savePopup() {
  if (isSaving.value) {
    return;
  }

  notice.value = '';
  showError('');

  const firstInvalid = validate();
  if (firstInvalid) {
    const count = Object.keys(fieldErrors.value).length;
    showError(
      sprintf(
        /* translators: %d: number of form fields with a problem. */ i18n._n(
          'Fix the %d highlighted field, then save again.',
          'Fix the %d highlighted fields, then save again.',
          count,
          'simple-image-popup',
        ),
        count,
      ),
      true,
    );
    await nextTick();
    document.getElementById(firstInvalid)?.focus();
    return;
  }

  isSaving.value = true;
  const endpoint = form.value.id ? `/popups/${form.value.id}` : '/popups';
  const method = form.value.id ? 'PUT' : 'POST';

  try {
    const savedPopup = await request(endpoint, {
      method,
      body: JSON.stringify(form.value),
    });

    const index = popups.value.findIndex((popup) => popup.id === savedPopup.id);
    if (index >= 0) {
      popups.value.splice(index, 1, savedPopup);
    } else {
      popups.value.unshift(savedPopup);
    }

    activeId.value = savedPopup.id;
    form.value = clonePopup(savedPopup);
    savedSnapshot.value = snapshotOf(form.value);
    notice.value = sprintf(/* translators: %s: where the popup now shows, e.g. "Live on every page". */ i18n.__('Saved. %s.', 'simple-image-popup'), getVisibility(savedPopup).label);
  } catch (error) {
    showError(sprintf(/* translators: %s: error message from the server. */ i18n.__('Couldn’t save: %s', 'simple-image-popup'), error.message));
  } finally {
    isSaving.value = false;
  }
}

async function deletePopup() {
  if (!form.value.id) {
    return;
  }
  if (
    !window.confirm(
      sprintf(/* translators: %s: popup title. */ i18n.__('Delete “%s” permanently? This can’t be undone.', 'simple-image-popup'), currentTitle.value),
    )
  ) {
    return;
  }

  isDeleting.value = true;
  showError('');
  notice.value = '';
  const deletedTitle = currentTitle.value;
  const deletedId = form.value.id;
  const index = popups.value.findIndex((popup) => popup.id === deletedId);

  try {
    await request(`/popups/${deletedId}`, {
      method: 'DELETE',
    });

    popups.value = popups.value.filter((popup) => popup.id !== deletedId);
    loadPopup(popups.value[Math.min(index, popups.value.length - 1)] ?? null);
    notice.value = sprintf(/* translators: %s: popup title. */ i18n.__('“%s” deleted.', 'simple-image-popup'), deletedTitle);
    await nextTick();
    editorHeading.value?.focus();
  } catch (error) {
    showError(sprintf(/* translators: %s: error message from the server. */ i18n.__('Couldn’t delete: %s', 'simple-image-popup'), error.message));
  } finally {
    isDeleting.value = false;
  }
}

async function searchContent() {
  if (isLoadingResults.value) {
    return;
  }
  isLoadingResults.value = true;

  try {
    const payload = await request('/content-search', {}, { search: searchQuery.value.trim() });
    searchResults.value = Array.isArray(payload.items) ? payload.items : [];
  } catch (error) {
    searchResults.value = [];
    showError(sprintf(/* translators: %s: error message from the server. */ i18n.__('Search failed: %s', 'simple-image-popup'), error.message));
  } finally {
    hasSearched.value = true;
    isLoadingResults.value = false;
  }
}

function isTargeted(postId) {
  return form.value.targetPostIds.includes(postId);
}

function selectTarget(post) {
  if (isTargeted(post.id)) {
    return;
  }

  form.value.targetPostIds.push(post.id);
  form.value.targetPosts.push(post);
}

async function removeTarget(postId) {
  const index = form.value.targetPosts.findIndex((post) => post.id === postId);
  form.value.targetPostIds = form.value.targetPostIds.filter((id) => id !== postId);
  form.value.targetPosts = form.value.targetPosts.filter((post) => post.id !== postId);

  // Keep keyboard focus in the list instead of dropping it to the page.
  await nextTick();
  const buttons = document.querySelectorAll('.sip-targets__remove');
  const next = buttons[Math.min(index, buttons.length - 1)];
  (next || document.getElementById('sip-target-search'))?.focus();
}

function openMediaFrame() {
  if (!window.wp?.media) {
    showError(
      i18n.__(
        'The WordPress media library didn’t load. Paste an image URL instead, or reload the page.',
        'simple-image-popup',
      ),
    );
    return;
  }

  const frame = window.wp.media({
    title: i18n.__('Choose popup image', 'simple-image-popup'),
    button: {
      text: i18n.__('Use this image', 'simple-image-popup'),
    },
    library: {
      type: 'image',
    },
    multiple: false,
  });

  frame.on('select', () => {
    const attachment = frame.state().get('selection').first()?.toJSON();

    if (!attachment) {
      return;
    }

    form.value.imageId = Number(attachment.id || 0);
    form.value.imageUrl = attachment?.sizes?.full?.url || attachment.url || '';
    imageFailed.value = false;
  });

  frame.open();
}

function onImageUrlInput() {
  // A typed URL replaces the media library choice; otherwise the server keeps the old attachment.
  form.value.imageId = 0;
  imageFailed.value = false;
}

async function removeImage() {
  form.value.imageId = 0;
  form.value.imageUrl = '';
  imageFailed.value = false;
  await nextTick();
  document.getElementById('sip-choose-image')?.focus();
}

// Clear a field's error as soon as the user fixes it.
watch(
  form,
  (popup) => {
    const errors = fieldErrors.value;
    if (errors.title && popup.title.trim()) delete errors.title;
    if (errors.image && popup.imageUrl.trim()) delete errors.image;
    if (errors.linkUrl && (!popup.linkUrl.trim() || isHttpUrl(popup.linkUrl.trim()))) delete errors.linkUrl;
    if (errors.delaySeconds && popup.delaySeconds >= 0) delete errors.delaySeconds;
    if (errors.repeatMinutes && popup.repeatMinutes >= 0) delete errors.repeatMinutes;
    if (errors.maxWidth && popup.maxWidth >= 200) delete errors.maxWidth;
    if (Object.keys(errors).length === 0 && errorIsValidation.value) {
      showError('');
    }
  },
  { deep: true },
);

watch(isDirty, (dirty) => {
  if (dirty) {
    notice.value = '';
  }
});

function onKeydown(event) {
  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
    event.preventDefault();
    savePopup();
  }
}

function onBeforeUnload(event) {
  if (isDirty.value) {
    event.preventDefault();
    event.returnValue = '';
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeydown);
  window.addEventListener('beforeunload', onBeforeUnload);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown);
  window.removeEventListener('beforeunload', onBeforeUnload);
});
</script>

<template>
  <div class="sip-admin">
    <div class="sip-admin__page-header">
      <h1 class="wp-heading-inline">{{ pageTitle }}</h1>
      <button class="page-title-action" type="button" @click="createPopup">
        {{ i18n.__('Add popup', 'simple-image-popup') }}
      </button>
      <hr class="wp-header-end" />
    </div>

    <div class="sip-admin__layout">
      <nav class="sip-admin__sidebar" aria-labelledby="sip-list-heading">
        <h2 id="sip-list-heading" class="sip-admin__sidebar-heading">
          {{ i18n.__('Popups', 'simple-image-popup') }}
          <span class="sip-admin__count">({{ popups.length.toLocaleString() }})</span>
        </h2>

        <p v-if="popups.length === 0" class="sip-admin__empty">
          {{ i18n.__('No popups yet. Fill in the form to create your first one.', 'simple-image-popup') }}
        </p>

        <ul class="sip-admin__list">
          <li v-if="activeId === 0">
            <button type="button" class="sip-admin__popup-item is-active" aria-current="true">
              <span class="sip-admin__thumb" aria-hidden="true">
                <img v-if="form.imageUrl && !imageFailed" :src="form.imageUrl" alt="" />
              </span>
              <span class="sip-admin__popup-text">
                <span class="sip-admin__popup-title">
                  {{ form.title.trim() || i18n.__('New popup', 'simple-image-popup') }}
                </span>
                <span class="sip-admin__popup-meta">{{ i18n.__('Not saved yet', 'simple-image-popup') }}</span>
              </span>
            </button>
          </li>
          <li v-for="popup in popups" :key="popup.id">
            <button
              type="button"
              class="sip-admin__popup-item"
              :class="{ 'is-active': popup.id === activeId }"
              :aria-current="popup.id === activeId ? 'true' : undefined"
              @click="selectPopup(popup)"
            >
              <span class="sip-admin__thumb" aria-hidden="true">
                <img v-if="popup.imageUrl" :src="popup.imageUrl" alt="" loading="lazy" />
              </span>
              <span class="sip-admin__popup-text">
                <span class="sip-admin__popup-title">
                  {{ popup.title || i18n.__('Untitled popup', 'simple-image-popup') }}
                </span>
                <span class="sip-admin__popup-meta">
                  <span class="sip-status" :class="`sip-status--${getVisibility(popup).tone}`">
                    {{ getVisibility(popup).short }}
                  </span>
                  <span aria-hidden="true">·</span>
                  <span>{{ formatViews(popup.views) }}</span>
                </span>
              </span>
            </button>
          </li>
        </ul>
      </nav>

      <form
        class="sip-admin__editor"
        novalidate
        aria-labelledby="sip-editor-heading"
        :aria-busy="isSaving ? 'true' : undefined"
        @submit.prevent="savePopup"
      >
        <div class="sip-admin__toolbar">
          <div class="sip-admin__toolbar-title">
            <h2 id="sip-editor-heading" ref="editorHeading" tabindex="-1">{{ currentTitle }}</h2>
            <p class="sip-status sip-status--large" :class="`sip-status--${visibility.tone}`">
              {{ visibility.label }}
            </p>
          </div>
          <div class="sip-admin__toolbar-actions">
            <span v-if="isDirty" class="sip-admin__dirty">
              <span class="sip-admin__dirty-text">{{ i18n.__('Unsaved changes', 'simple-image-popup') }}</span>
            </span>
            <button
              class="button button-primary"
              type="submit"
              :disabled="isSaving"
              aria-keyshortcuts="Control+S Meta+S"
              :title="i18n.__('Save (Ctrl+S)', 'simple-image-popup')"
            >
              <template v-if="isSaving">{{ i18n.__('Saving…', 'simple-image-popup') }}</template>
              <template v-else-if="form.id">{{ i18n.__('Save changes', 'simple-image-popup') }}</template>
              <template v-else>{{ i18n.__('Create popup', 'simple-image-popup') }}</template>
            </button>
          </div>
        </div>

        <div class="sip-admin__messages">
          <div role="status">
            <p v-if="notice" class="sip-message sip-message--success">{{ notice }}</p>
          </div>
          <div role="alert">
            <p v-if="errorMessage" class="sip-message sip-message--error">{{ errorMessage }}</p>
          </div>
        </div>

        <p v-if="form.isMigrated" class="sip-message sip-message--info">
          {{ i18n.__('This popup was migrated automatically from the legacy settings screen.', 'simple-image-popup') }}
        </p>

        <section class="sip-panel" aria-labelledby="sip-content-heading">
          <h3 id="sip-content-heading" class="sip-panel__heading">{{ i18n.__('Content', 'simple-image-popup') }}</h3>

          <div class="sip-field" :class="{ 'has-error': fieldErrors.title }">
            <label for="sip-title" class="sip-field__label">
              {{ i18n.__('Title', 'simple-image-popup') }}
              <span class="sip-field__required">{{ i18n.__('(required)', 'simple-image-popup') }}</span>
            </label>
            <input
              id="sip-title"
              v-model="form.title"
              type="text"
              autocomplete="off"
              :aria-invalid="fieldErrors.title ? 'true' : undefined"
              :aria-describedby="describedBy(errorId('title'), 'sip-title-help')"
            />
            <p v-if="fieldErrors.title" id="sip-error-title" class="sip-field__error">{{ fieldErrors.title }}</p>
            <p id="sip-title-help" class="sip-field__help">
              {{
                i18n.__(
                  'Also read out by screen readers as the image description, so describe what the image says or shows.',
                  'simple-image-popup',
                )
              }}
            </p>
          </div>

          <fieldset class="sip-field" :class="{ 'has-error': fieldErrors.image }">
            <legend class="sip-field__label">
              {{ i18n.__('Image', 'simple-image-popup') }}
              <span class="sip-field__required">{{ i18n.__('(required)', 'simple-image-popup') }}</span>
            </legend>

            <div class="sip-image">
              <div class="sip-image__preview" :class="{ 'is-empty': !form.imageUrl || imageFailed }">
                <img
                  v-if="form.imageUrl && !imageFailed"
                  :src="form.imageUrl"
                  :alt="i18n.__('Preview of the popup image', 'simple-image-popup')"
                  @error="imageFailed = true"
                />
                <span v-else-if="imageFailed" class="sip-image__placeholder">
                  {{ i18n.__('This image couldn’t be loaded. Check the URL.', 'simple-image-popup') }}
                </span>
                <span v-else class="sip-image__placeholder">{{ i18n.__('No image chosen', 'simple-image-popup') }}</span>
              </div>

              <div class="sip-image__controls">
                <button
                  id="sip-choose-image"
                  class="button"
                  type="button"
                  :aria-describedby="describedBy(errorId('image'))"
                  @click="openMediaFrame"
                >
                  <template v-if="form.imageUrl">{{ i18n.__('Replace image', 'simple-image-popup') }}</template>
                  <template v-else>{{ i18n.__('Choose from media library', 'simple-image-popup') }}</template>
                </button>
                <button
                  v-if="form.imageUrl"
                  class="button-link button-link-delete"
                  type="button"
                  @click="removeImage"
                >
                  {{ i18n.__('Remove image', 'simple-image-popup') }}
                </button>
              </div>
            </div>

            <p v-if="fieldErrors.image" id="sip-error-image" class="sip-field__error">{{ fieldErrors.image }}</p>

            <div class="sip-field sip-field--nested">
              <label for="sip-image-url" class="sip-field__label sip-field__label--secondary">
                {{ i18n.__('Or paste an image URL', 'simple-image-popup') }}
              </label>
              <input
                id="sip-image-url"
                v-model.trim="form.imageUrl"
                type="url"
                inputmode="url"
                spellcheck="false"
                placeholder="https://example.com/popup.jpg"
                :aria-invalid="fieldErrors.image ? 'true' : undefined"
                :aria-describedby="describedBy(errorId('image'))"
                @input="onImageUrlInput"
              />
            </div>
          </fieldset>

          <div class="sip-field" :class="{ 'has-error': fieldErrors.linkUrl }">
            <label for="sip-link-url" class="sip-field__label">
              {{ i18n.__('Link', 'simple-image-popup') }}
              <span class="sip-field__optional">{{ i18n.__('(optional)', 'simple-image-popup') }}</span>
            </label>
            <input
              id="sip-link-url"
              v-model.trim="form.linkUrl"
              type="url"
              inputmode="url"
              spellcheck="false"
              placeholder="https://example.com/offer"
              :aria-invalid="fieldErrors.linkUrl ? 'true' : undefined"
              :aria-describedby="describedBy(errorId('linkUrl'), 'sip-link-help')"
            />
            <p v-if="fieldErrors.linkUrl" id="sip-error-linkUrl" class="sip-field__error">{{ fieldErrors.linkUrl }}</p>
            <p id="sip-link-help" class="sip-field__help">
              {{ i18n.__('Where visitors go when they click the image.', 'simple-image-popup') }}
            </p>
          </div>
        </section>

        <section class="sip-panel" aria-labelledby="sip-visibility-heading">
          <h3 id="sip-visibility-heading" class="sip-panel__heading">
            {{ i18n.__('Visibility', 'simple-image-popup') }}
          </h3>

          <div class="sip-field-row">
            <div class="sip-field">
              <label for="sip-status" class="sip-field__label">{{ i18n.__('Status', 'simple-image-popup') }}</label>
              <select id="sip-status" v-model="form.status" aria-describedby="sip-status-help">
                <option value="draft">{{ i18n.__('Draft', 'simple-image-popup') }}</option>
                <option value="publish">{{ i18n.__('Published', 'simple-image-popup') }}</option>
              </select>
              <p id="sip-status-help" class="sip-field__help">
                {{ i18n.__('Drafts never appear on your site.', 'simple-image-popup') }}
              </p>
            </div>

            <div class="sip-field sip-field--check">
              <div class="sip-check">
                <input id="sip-enabled" v-model="form.enabled" type="checkbox" aria-describedby="sip-enabled-help" />
                <label for="sip-enabled">{{ i18n.__('Enabled', 'simple-image-popup') }}</label>
              </div>
              <p id="sip-enabled-help" class="sip-field__help sip-field__help--indented">
                {{ i18n.__('Untick to pause a published popup without unpublishing it.', 'simple-image-popup') }}
              </p>
            </div>
          </div>

          <fieldset class="sip-field">
            <legend class="sip-field__label">{{ i18n.__('Where it appears', 'simple-image-popup') }}</legend>
            <div class="sip-choices">
              <div class="sip-check">
                <input id="sip-target-all" v-model="form.targetMode" type="radio" value="all" name="sip-target-mode" />
                <label for="sip-target-all">{{ i18n.__('Every page on the site', 'simple-image-popup') }}</label>
              </div>
              <div class="sip-check">
                <input
                  id="sip-target-selected"
                  v-model="form.targetMode"
                  type="radio"
                  value="selected"
                  name="sip-target-mode"
                />
                <label for="sip-target-selected">
                  {{ i18n.__('Only on posts and pages I choose', 'simple-image-popup') }}
                </label>
              </div>
            </div>
          </fieldset>

          <div v-if="form.targetMode === 'selected'" class="sip-targets">
            <div class="sip-field">
              <label for="sip-target-search" class="sip-field__label">
                {{ i18n.__('Find posts and pages', 'simple-image-popup') }}
              </label>
              <div class="sip-search-row">
                <input
                  id="sip-target-search"
                  v-model="searchQuery"
                  type="search"
                  autocomplete="off"
                  :placeholder="i18n.__('Search by title', 'simple-image-popup')"
                  aria-describedby="sip-search-status"
                  @keydown.enter.prevent="searchContent"
                />
                <button class="button" type="button" :disabled="isLoadingResults" @click="searchContent">
                  {{ i18n.__('Search', 'simple-image-popup') }}
                </button>
              </div>
              <p id="sip-search-status" class="sip-field__help" aria-live="polite">
                {{ searchStatus || i18n.__('Search with the box empty to list posts and pages.', 'simple-image-popup') }}
              </p>
            </div>

            <ul
              v-if="searchResults.length"
              class="sip-results"
              :aria-label="i18n.__('Search results', 'simple-image-popup')"
            >
              <li v-for="result in searchResults" :key="result.id" class="sip-results__item">
                <span class="sip-results__title">
                  {{ titleOf(result) }}
                  <span class="sip-type">{{ typeLabels[result.type] || result.type }}</span>
                </span>
                <button
                  class="button button-small"
                  type="button"
                  :disabled="isTargeted(result.id)"
                  :aria-label="addLabel(result)"
                  @click="selectTarget(result)"
                >
                  <template v-if="isTargeted(result.id)">{{ i18n.__('Added', 'simple-image-popup') }}</template>
                  <template v-else>{{ i18n._x('Add', 'add a page to the popup', 'simple-image-popup') }}</template>
                </button>
              </li>
            </ul>

            <h4 id="sip-targets-heading" class="sip-targets__heading">{{ targetsHeading }}</h4>
            <ul v-if="form.targetPosts.length" class="sip-targets__list" aria-labelledby="sip-targets-heading">
              <li v-for="target in form.targetPosts" :key="target.id" class="sip-results__item">
                <span class="sip-results__title">
                  {{ titleOf(target) }}
                  <span class="sip-type">{{ typeLabels[target.type] || target.type }}</span>
                </span>
                <button
                  class="button-link button-link-delete sip-targets__remove"
                  type="button"
                  :aria-label="removeLabel(target)"
                  @click="removeTarget(target.id)"
                >
                  {{ i18n._x('Remove', 'remove a page from the popup', 'simple-image-popup') }}
                </button>
              </li>
            </ul>
            <p v-else class="sip-message sip-message--warning">
              {{
                i18n.__(
                  'No pages chosen yet, so this popup won’t appear anywhere. Search above and add at least one.',
                  'simple-image-popup',
                )
              }}
            </p>
          </div>
        </section>

        <section class="sip-panel" aria-labelledby="sip-behaviour-heading">
          <h3 id="sip-behaviour-heading" class="sip-panel__heading">
            {{ i18n.__('Timing and behaviour', 'simple-image-popup') }}
          </h3>

          <div class="sip-field-row sip-field-row--thirds">
            <div class="sip-field" :class="{ 'has-error': fieldErrors.delaySeconds }">
              <label for="sip-delay" class="sip-field__label">
                {{ i18n.__('Show after', 'simple-image-popup') }}
                <span class="screen-reader-text">{{ i18n.__('(seconds)', 'simple-image-popup') }}</span>
              </label>
              <div class="sip-unit">
                <input
                  id="sip-delay"
                  v-model.number="form.delaySeconds"
                  type="number"
                  min="0"
                  step="1"
                  inputmode="numeric"
                  :aria-invalid="fieldErrors.delaySeconds ? 'true' : undefined"
                  :aria-describedby="describedBy(errorId('delaySeconds'), 'sip-delay-help')"
                />
                <span class="sip-unit__suffix" aria-hidden="true">
                  {{ i18n._x('seconds', 'unit after a number field', 'simple-image-popup') }}
                </span>
              </div>
              <p v-if="fieldErrors.delaySeconds" id="sip-error-delaySeconds" class="sip-field__error">
                {{ fieldErrors.delaySeconds }}
              </p>
              <p id="sip-delay-help" class="sip-field__help">
                {{ i18n.__('How long to wait after the page loads.', 'simple-image-popup') }}
              </p>
            </div>

            <div class="sip-field" :class="{ 'has-error': fieldErrors.repeatMinutes }">
              <label for="sip-repeat" class="sip-field__label">
                {{ i18n.__('Show again after', 'simple-image-popup') }}
                <span class="screen-reader-text">{{ i18n.__('(minutes)', 'simple-image-popup') }}</span>
              </label>
              <div class="sip-unit">
                <input
                  id="sip-repeat"
                  v-model.number="form.repeatMinutes"
                  type="number"
                  min="0"
                  step="1"
                  inputmode="numeric"
                  :aria-invalid="fieldErrors.repeatMinutes ? 'true' : undefined"
                  :aria-describedby="describedBy(errorId('repeatMinutes'), 'sip-repeat-help')"
                />
                <span class="sip-unit__suffix" aria-hidden="true">
                  {{ i18n._x('minutes', 'unit after a number field', 'simple-image-popup') }}
                </span>
              </div>
              <p v-if="fieldErrors.repeatMinutes" id="sip-error-repeatMinutes" class="sip-field__error">
                {{ fieldErrors.repeatMinutes }}
              </p>
              <p id="sip-repeat-help" class="sip-field__help">
                {{ repeatHelp }} {{ i18n.__('Use 0 to show it every time.', 'simple-image-popup') }}
              </p>
            </div>

            <div class="sip-field" :class="{ 'has-error': fieldErrors.maxWidth }">
              <label for="sip-max-width" class="sip-field__label">
                {{ i18n.__('Maximum width', 'simple-image-popup') }}
                <span class="screen-reader-text">{{ i18n.__('(pixels)', 'simple-image-popup') }}</span>
              </label>
              <div class="sip-unit">
                <input
                  id="sip-max-width"
                  v-model.number="form.maxWidth"
                  type="number"
                  min="200"
                  step="10"
                  inputmode="numeric"
                  :aria-invalid="fieldErrors.maxWidth ? 'true' : undefined"
                  :aria-describedby="describedBy(errorId('maxWidth'), 'sip-max-width-help')"
                />
                <span class="sip-unit__suffix" aria-hidden="true">
                  {{ i18n._x('px', 'pixels, unit after a number field', 'simple-image-popup') }}
                </span>
              </div>
              <p v-if="fieldErrors.maxWidth" id="sip-error-maxWidth" class="sip-field__error">
                {{ fieldErrors.maxWidth }}
              </p>
              <p id="sip-max-width-help" class="sip-field__help">{{ i18n.__('200 or more.', 'simple-image-popup') }}</p>
            </div>
          </div>

          <div class="sip-field">
            <div class="sip-check">
              <input
                id="sip-click-close"
                v-model="form.clickToClose"
                type="checkbox"
                aria-describedby="sip-click-close-help"
              />
              <label for="sip-click-close">
                {{ i18n.__('Close when the image or background is clicked', 'simple-image-popup') }}
              </label>
            </div>
            <p id="sip-click-close-help" class="sip-field__help sip-field__help--indented">
              {{ i18n.__('Visitors can always close it with the close button or the Esc key.', 'simple-image-popup') }}
            </p>
          </div>

          <div class="sip-field">
            <label for="sip-display-key" class="sip-field__label">{{ i18n.__('Display key', 'simple-image-popup') }}</label>
            <input
              id="sip-display-key"
              v-model="form.displayKey"
              class="sip-field__input--medium"
              type="text"
              spellcheck="false"
              autocomplete="off"
              placeholder="spring-sale-popup"
              aria-describedby="sip-display-key-help"
            />
            <p id="sip-display-key-help" class="sip-field__help">
              {{
                i18n.__(
                  'Remembers who has already seen this popup. Change it to show the popup to everyone again. Use lowercase letters, numbers and hyphens, or leave it empty to generate one.',
                  'simple-image-popup',
                )
              }}
            </p>
          </div>
        </section>

        <div v-if="form.id" class="sip-admin__footer">
          <p class="sip-admin__footer-meta">{{ formatViewsSoFar(form.views) }}</p>
          <button class="button-link button-link-delete" type="button" :disabled="isDeleting" @click="deletePopup">
            <template v-if="isDeleting">{{ i18n.__('Deleting…', 'simple-image-popup') }}</template>
            <template v-else>{{ i18n.__('Delete popup', 'simple-image-popup') }}</template>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
