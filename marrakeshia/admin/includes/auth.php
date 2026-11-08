<?php
/**
 * حماية صفحات لوحة التحكم
 */
require_once dirname(__DIR__, 2) . '/includes/functions.php';

if (!is_admin()) {
    if (is_logged_in()) {
        // حساب عميلة يحاول فتح لوحة المدير مباشرة: يُمنع ويُعاد إلى صفحته
        flash('error', 'هذه المنطقة مخصصة للمدير فقط، ولا تملكين صلاحية الوصول إلى لوحة التحكم.');
        redirect(url('account.php'));
    }
    flash('error', 'يرجى تسجيل الدخول بحساب مدير للوصول إلى لوحة التحكم.');
    redirect(url('admin/login.php'));
}

$admin = current_user();
