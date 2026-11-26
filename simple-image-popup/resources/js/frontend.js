const config = window.SimpleImagePopupFrontend || {};
const queue = [];
let activePopup = null;
let activeCleanup = null;
let activeFocus = null;

function getFrequencyStorageKey(popup) {
  return `sip-popup:${popup.displayKey || `popup-${popup.id}`}`;
}

function shouldDisplayPopup(popup) {
  if (!popup.repeatMinutes || popup.repeatMinutes <= 0) {
    return true;
  }

  try {
    const nextDisplayAt = Number(window.localStorage.getItem(getFrequencyStorageKey(popup)) || '0');
    return !Number.isFinite(nextDisplayAt) || Date.now() >= nextDisplayAt;
  } catch {
    return true;
  }
}

function markPopupDisplayed(popup) {
  if (!popup.repeatMinutes || popup.repeatMinutes <= 0) {
    return;
  }

  const nextDisplayAt = Date.now() + popup.repeatMinutes * 60 * 1000;
  try {
    window.localStorage.setItem(getFrequencyStorageKey(popup), String(nextDisplayAt));
  } catch {
    // Display the popup even when the browser blocks storage.
  }
}

function closeActivePopup() {
  if (!activePopup) {
    return;
  }

  activePopup.element.hidden = true;
  document.body.classList.remove('simple-image-popup-plugin--open');

  if (typeof activeCleanup === 'function') {
    activeCleanup();
  }

  if (activeFocus && typeof activeFocus.focus === 'function') {
    activeFocus.focus();
  }

  activePopup = null;
  activeCleanup = null;
  activeFocus = null;

  const nextPopup = queue.shift();

  if (nextPopup) {
    showPopup(nextPopup);
  }
}

function bindPopupEvents(popup, element) {
  const handleKeydown = (event) => {
    if (event.key === 'Escape') {
      closeActivePopup();
    } else if (event.key === 'Tab') {
      const controls = [...element.querySelectorAll('button, a[href]')];
      const focused = document.activeElement;

      if (event.shiftKey && (focused === controls[0] || !controls.includes(focused))) {
        event.preventDefault();
        controls[controls.length - 1]?.focus();
      } else if (!event.shiftKey && focused === controls[controls.length - 1]) {
        event.preventDefault();
        controls[0]?.focus();
      }
    }
  };

  const handleClick = (event) => {
    const target = event.target instanceof Element ? event.target.closest('[data-popup-close]') : null;

    if (!target) {
      return;
    }

    if (target.dataset.popupClose === 'button') {
      closeActivePopup();
      return;
    }

    if (
      popup.clickToClose &&
      (target.dataset.popupClose === 'image' || target.dataset.popupClose === 'backdrop')
    ) {
      closeActivePopup();
    }
  };

  document.addEventListener('keydown', handleKeydown);
  element.addEventListener('click', handleClick);

  return () => {
    document.removeEventListener('keydown', handleKeydown);
    element.removeEventListener('click', handleClick);
  };
}

async function trackView(popup) {
  try {
    await fetch(config.trackUrl, {
      method: 'POST',
      credentials: 'omit',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ popupId: popup.id, token: popup.viewToken }),
    });
  } catch (error) {
    // Tracking failures should not block popup display.
  }
}

function showPopup(popup) {
  const element = document.getElementById(`simple-image-popup-${popup.id}`);

  if (!element) {
    return;
  }

  activeFocus = document.activeElement;
  activePopup = { popup, element };
  activeCleanup = bindPopupEvents(popup, element);

  markPopupDisplayed(popup);
  element.hidden = false;
  document.body.classList.add('simple-image-popup-plugin--open');

  const dialog = element.querySelector('.simple-image-popup-plugin__inner');

  if (dialog instanceof HTMLElement) {
    dialog.focus();
  }

  trackView(popup);
}

function queuePopup(popup) {
  const delay = Math.max(0, Number(popup.delaySeconds || 0)) * 1000;

  window.setTimeout(() => {
    if (activePopup) {
      queue.push(popup);
      return;
    }

    showPopup(popup);
  }, delay);
}

function init() {
  const popups = Array.isArray(config.popups) ? config.popups : [];

  popups
    .filter((popup) => popup?.id && shouldDisplayPopup(popup))
    .forEach((popup) => queuePopup(popup));
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
