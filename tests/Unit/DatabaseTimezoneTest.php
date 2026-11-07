<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regression: cPanel MariaDB runs in local Australian time. Without pinning the
 * session to UTC, TIMESTAMPs are reinterpreted and inserts inside the October
 * daylight-saving gap (02:00-02:59 local) fail with "Incorrect datetime value".
 */
class DatabaseTimezoneTest extends TestCase
{
    public function test_mysql_family_connections_use_utc_sessions(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('+00:00', config('database.connections.mariadb.timezone'));
        $this->assertSame('+00:00', config('database.connections.mysql.timezone'));
    }
}
