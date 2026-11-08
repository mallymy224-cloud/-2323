<?php
require_once __DIR__ . '/includes/functions.php';

logout_user();
flash('info', 'تم تسجيل خروجك بنجاح. ننتظرك قريباً!');
redirect(url('index.php'));
