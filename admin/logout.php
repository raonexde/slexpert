<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if (request_is_post()) verify_csrf();
admin_logout();
redirect('admin/login.php');
