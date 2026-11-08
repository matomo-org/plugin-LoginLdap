/*!
 * Matomo - free/libre analytics platform
 *
 * @link https://matomo.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

import { NotificationsStore } from 'CoreHome';

/**
 * Shows the message of an API error answered with a 4xx status, such as a wrong password
 * confirmation, and returns it; returns null for anything else. Before Matomo 6.1.0, AjaxHelper only
 * shows its generic error box for a 4xx. From 6.1.0 it shows the message itself and the error never
 * reaches this helper, so remove this file once the plugin requires Matomo 6.1.0 or later.
 */
export default function showHttpErrorMessage(error: unknown): string|null {
  const xhr = error as { status?: unknown, responseJSON?: { result?: unknown, message?: unknown } };
  if (!xhr || typeof xhr !== 'object' || typeof xhr.status !== 'number'
    || xhr.status < 400 || xhr.status >= 500
  ) {
    return null;
  }

  const message = xhr.responseJSON?.result === 'error' ? xhr.responseJSON.message : null;
  if (typeof message !== 'string' || !message) {
    return null;
  }

  // AjaxHelper's default error callback has already shown it; the message below says more
  const genericError = document.getElementById('loadingError');
  if (genericError) {
    genericError.style.display = 'none';
  }

  // AjaxHelper's own id, so this is shown and replaced like an error it shows itself
  const notificationId = NotificationsStore.show({
    message,
    context: 'error',
    id: 'ajaxHelper',
    type: 'toast',
  });
  NotificationsStore.scrollToNotification(notificationId);

  return message;
}
