<?php

namespace Drupal\social_private_message\Plugin\Block;

use Drupal\Core\Url;
use Drupal\private_message\Plugin\Block\PrivateMessageInboxBlock;

/**
 * Provides a 'SocialPrivateMessageInboxBlock' block.
 *
 * @Block(
 *   id = "social_private_message_inbox_block",
 *   admin_label = @Translation("Social Private Message Inbox"),
 * )
 */
class SocialPrivateMessageInboxBlock extends PrivateMessageInboxBlock {

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($this->currentUser->isAuthenticated() && $this->currentUser->hasPermission('use private messaging system')) {
      $config = $this->getConfiguration();
      $thread_info = $this->privateMessageService->getThreadsForUser($config['thread_count']);

      if (count($thread_info['threads'])) {

        $view_builder = $this->entityManager->getViewBuilder('private_message_thread');
        $threads = $thread_info['threads'];

        /* @var \Drupal\private_message\Entity\PrivateMessageThread $thread */
        // This custom sort, sorts based on newestmessage timestamp in the thread.
        uasort($threads, array($this, "custom_sort"));
        // The above sorts ascending... so:
        $threads = array_reverse($threads);

        foreach ($threads as $thread) {
          $block[$thread->id()] = $view_builder->view($thread, 'inbox');
        }

        $block['#attached']['library'][] = 'private_message/inbox_block';
        if (count($threads) && $thread_info['next_exists']) {
          $prev_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_old_inbox_threads']);
          $prev_token = $this->csrfToken->get($prev_url->getInternalPath());
          $prev_url->setOptions(['absolute' => TRUE, 'query' => ['token' => $prev_token]]);

          $new_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_inbox_threads']);
          $new_token = $this->csrfToken->get($new_url->getInternalPath());
          $new_url->setOptions(['absolute' => TRUE, 'query' => ['token' => $new_token]]);

          $last_thread = array_pop($threads);
          $block['#attached']['drupalSettings']['privateMessageInboxBlock'] = [
            'oldestTimestamp' => $last_thread->get('updated')->value,
            'loadPrevUrl' => $prev_url->toString(),
            'loadNewUrl' => $new_url->toString(),
            'threadCount' => $config['ajax_load_count'],
          ];
        }
        else {
          $block['#attached']['drupalSettings']['privateMessageInboxBlock'] = [
            'oldestTimestamp' => FALSE,
          ];
        }
      }
      else {
        $block['no_threads'] = [
          '#prefix' => '<p>',
          '#suffix' => '</p>',
          '#markup' => $this->t('You do not have any private messages'),
        ];
      }

      $new_url = Url::fromRoute('private_message.ajax_callback', ['op' => 'get_new_inbox_threads']);
      $new_token = $this->csrfToken->get($new_url->getInternalPath());
      $new_url->setOptions(['absolute' => TRUE, 'query' => ['token' => $new_token]]);

      $block['#attached']['drupalSettings']['privateMessageInboxBlock']['loadNewUrl'] = $new_url->toString();

      $config = $this->getConfiguration();
      $block['#attached']['drupalSettings']['privateMessageInboxBlock']['ajaxRefreshRate'] = $config['ajax_refresh_rate'];

      // Add the default classes, as these are not added when the block output is overridden with
      // a template
      $block['#attributes']['class'][] = 'block';
      $block['#attributes']['class'][] = 'block-private-message';
      $block['#attributes']['class'][] = 'block-private-message-inbox-block';

      return $block;
    }
  }

  public function custom_sort($pmt1, $pmt2) {

    /* @var \Drupal\private_message\Entity\PrivateMessageThread $pmt1 */
    /* @var \Drupal\private_message\Entity\PrivateMessageThread $pmt2 */
    if ($pmt1->getUpdatedTime() == $pmt2->getUpdatedTime()) {
      return 0;
    }
    return ($pmt1->getUpdatedTime() < $pmt2->getUpdatedTime()) ? -1 : 1;
  }

}
