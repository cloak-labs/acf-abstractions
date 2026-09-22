<?php

declare(strict_types=1);

namespace CloakWP\ACF\Tests\Query;

use CloakWP\ACF\Query\DefaultPostOrder;
use PHPUnit\Framework\TestCase;

final class DefaultPostOrderTest extends TestCase
{
  public function testApplyFillsMissingOrderby(): void
  {
    $args = DefaultPostOrder::apply([
      'post_type' => 'team',
      'posts_per_page' => -1,
    ]);

    $this->assertSame(DefaultPostOrder::ORDERBY, $args['orderby']);
    $this->assertSame('team', $args['post_type']);
  }

  public function testApplyLeavesExplicitOrderbyAlone(): void
  {
    foreach (['rand', 'post__in', ['date' => 'ASC']] as $orderby) {
      $args = DefaultPostOrder::apply([
        'post_type' => 'post',
        'orderby' => $orderby,
      ]);

      $this->assertSame($orderby, $args['orderby']);
    }
  }

  public function testDateTiebreakerIsSkippedForDateAndRandom(): void
  {
    $this->assertSame(['date' => 'ASC'], DefaultPostOrder::withDateTiebreaker(['date' => 'ASC']));
    $this->assertSame(['rand' => 'DESC'], DefaultPostOrder::withDateTiebreaker(['rand' => 'DESC']));
  }

  public function testDateTiebreakerFollowsMenuOrderAndTitle(): void
  {
    $this->assertSame(
      ['menu_order' => 'ASC', 'date' => 'DESC'],
      DefaultPostOrder::withDateTiebreaker(['menu_order' => 'ASC']),
    );
    $this->assertSame(
      ['title' => 'ASC', 'date' => 'DESC'],
      DefaultPostOrder::withDateTiebreaker(['title' => 'ASC']),
    );
  }
}
