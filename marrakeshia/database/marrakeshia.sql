-- ============================================================
--  قاعدة بيانات متجر "مراكشية" Marrakeshia
--  الاستيراد: phpMyAdmin > Import > marrakeshia.sql
--  أو افتح: http://localhost/marrakeshia/install.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS `marrakeshia`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `marrakeshia`;

-- ------------------------------------------------------------
-- التصنيفات
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `product_sizes`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `sizes`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- المقاسات المتاحة
-- ------------------------------------------------------------
CREATE TABLE `sizes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(40) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_sizes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- المنتجات
-- ------------------------------------------------------------
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(180) NOT NULL,
  `short_description` VARCHAR(300) DEFAULT NULL,
  `description` TEXT,
  `details` TEXT,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `old_price` DECIMAL(10,2) DEFAULT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `image` VARCHAR(255) DEFAULT NULL,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','draft') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_products_category` (`category_id`),
  KEY `idx_products_status` (`status`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
    REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- المقاسات المتاحة لكل منتج
-- ------------------------------------------------------------
CREATE TABLE `product_sizes` (
  `product_id` INT UNSIGNED NOT NULL,
  `size_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`product_id`, `size_id`),
  CONSTRAINT `fk_ps_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ps_size` FOREIGN KEY (`size_id`)
    REFERENCES `sizes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- معرض صور المنتج (أكثر من صورة لكل منتج)
-- ------------------------------------------------------------
CREATE TABLE `product_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pi_product` (`product_id`),
  CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- المستخدمون (عملاء + مدير)
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(80) DEFAULT NULL,
  `role` ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- الطلبات
-- ------------------------------------------------------------
CREATE TABLE `orders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_number` VARCHAR(30) NOT NULL,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `customer_name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `email` VARCHAR(160) DEFAULT NULL,
  `city` VARCHAR(80) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `notes` TEXT,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `shipping` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(40) NOT NULL DEFAULT 'cod',
  `status` ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_orders_number` (`order_number`),
  KEY `idx_orders_status` (`status`),
  KEY `idx_orders_user` (`user_id`),
  CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- عناصر الطلب
-- ------------------------------------------------------------
CREATE TABLE `order_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `product_name` VARCHAR(180) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `quantity` INT NOT NULL DEFAULT 1,
  `size` VARCHAR(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_items_order` (`order_id`),
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`)
    REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_product` FOREIGN KEY (`product_id`)
    REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- الإعدادات
-- ------------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key` VARCHAR(60) NOT NULL,
  `setting_value` TEXT,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  البيانات الأولية
-- ============================================================

INSERT INTO `categories` (`id`, `name`, `description`, `image`, `sort_order`) VALUES
(1, 'قفاطين وعبايات', 'قطع تقليدية بلمسة عصرية تليق بكل مناسبة', 'p1.svg', 1),
(2, 'فساتين', 'فساتين صيفية وسهرات بقصّات أنيقة', 'p3.svg', 2),
(3, 'جلابيات', 'جلابيات مريحة بأقمشة طبيعية وتطريز يدوي', 'p5.svg', 3),
(4, 'قمصان وبلوزات', 'أساسيات أنيقة لإطلالات يومية', 'p7.svg', 4),
(5, 'أوشحة وإكسسوارات', 'لمسات أخيرة تكمل إطلالتك', 'p8.svg', 5);

INSERT INTO `sizes` (`id`, `name`, `sort_order`) VALUES
(1, 'XS', 1),
(2, 'S', 2),
(3, 'M', 3),
(4, 'L', 4),
(5, 'XL', 5),
(6, 'XXL', 6),
(7, 'مقاس واحد', 7);

INSERT INTO `products`
(`id`, `category_id`, `name`, `short_description`, `description`, `details`, `price`, `old_price`, `stock`, `image`, `featured`, `status`, `created_at`) VALUES
(1, 1, 'قفطان مراكش الملكي', 'قفطان مخملي مطرّز بخيوط ذهبية، قطعة تليق بالمناسبات الكبرى.', 'قفطان مراكش الملكي مصنوع من المخمل الفاخر، مطرّز يدوياً بخيوط ذهبية على الصدر والأكمام. قصّة واسعة تمنحك حرية الحركة وإطلالة ملكية، مثالي للأعراس والحفلات وحفلات العقيقة.', 'الخامة: مخمل فاخر\nالتطريز: خيوط ذهبية يدوية\nالمقاسات: S / M / L / XL\nيتضمن: قفطان + حزام مزين\nالعناية: تنظيف جاف فقط', 890.00, 1100.00, 8, 'p1.svg', 1, 'active', NOW() - INTERVAL 2 DAY),
(2, 1, 'عباية نورة المطرزة', 'عباية ساتان ناعمة بتطريز خفيف على الأكمام، أناقة هادئة لكل يوم.', 'عباية نورة مصنوعة من الساتان عالي الجودة، بتصميم انسيابي وتطريز ناعم على الأكمام. خفيفة ومريحة، مناسبة للمناسبات النهارية والخروج اليومي.', 'الخامة: ساتان\nالتطريز: خيطان ناعم على الأكمام\nالمقاسات: S / M / L / XL / XXL\nالطول: 140 سم تقريباً\nالعناية: غسيل يدوي بماء بارد', 650.00, NULL, 12, 'p2.svg', 1, 'active', NOW() - INTERVAL 3 DAY),
(3, 2, 'فستان زهرة الصحراء', 'فستان شيفون بنقشة زهور دقيقة وقصة واسعة مريحة.', 'فستان زهرة الصحراء بقصة A-Line تنسدل بلطف، مصنوع من الشيفون المبطّن. نقشة زهور دقيقة مستوحاة من واحات الصحراء، مع حزام خصر قابل للربط.', 'الخامة: شيفون مبطّن\nالقصة: A-Line\nالمقاسات: XS / S / M / L\nالطول: 125 سم\nالعناية: غسيل لطيف أو تنظيف جاف', 720.00, 850.00, 6, 'p3.svg', 1, 'active', NOW() - INTERVAL 4 DAY),
(4, 2, 'فستان ليلة أندلسية', 'فستان سهرة طويل بتفاصيل دانتيل وألوان دافئة.', 'فستان ليلة أندلسية يجمع بين الدانتيل الناعم والقماش الحريري، بقصة طويلة تبرز الجمال بلمسة أندلسية. متوفر بلون الطين الدافئ.', 'الخامة: حرير مع دانتيل\nالقصة: طويلة ضيقة عند الخصر\nالمقاسات: S / M / L\nالطول: 150 سم\nالعناية: تنظيف جاف', 980.00, NULL, 4, 'p4.svg', 0, 'active', NOW() - INTERVAL 5 DAY),
(5, 3, 'جلابية أطلس', 'جلابية كتان مريحة بتطريز أمازيغي على الياقة.', 'جلابية أطلس من الكتان الطبيعي، خفيفة وتسمح بمرور الهواء، بتطريز أمازيغي أصيل على الياقة والصدر. مثالية للبيت وللخروج النهاري.', 'الخامة: كتان طبيعي 100%\nالتطريز: أمازيغي يدوي\nالمقاسات: S / M / L / XL\nالعناية: غسيل على 30 درجة', 420.00, 490.00, 15, 'p5.svg', 1, 'active', NOW() - INTERVAL 6 DAY),
(6, 4, 'قميص كتان مراكشي', 'قميص كتان بقصة واسعة وأزرار خشبية، أساسي في كل خزانة.', 'قميص كتان مراكشي بقصة أوفرسايز مريحة وأزرار خشبية طبيعية. يناسب الإطلالات الكاجوال ويمكن تنسيقه مع الجلابية أو البنطلون.', 'الخامة: كتان\nالقصة: أوفرسايز\nالمقاسات: S / M / L / XL\nالألوان: بيج / أبيض\nالعناية: غسيل آلي لطيف', 260.00, NULL, 20, 'p6.svg', 0, 'active', NOW() - INTERVAL 7 DAY),
(7, 4, 'بلوزة ياسمين', 'بلوزة حريرية بأكمام منفوخة ورقبة على شكل V.', 'بلوزة ياسمين من الحرير الصناعي الناعم، بأكمام منفوخة خفيفة ورقبة V أنيقة. سهلة التنسيق مع التنانير والبنطلونات.', 'الخامة: حرير صناعي\nالقصة: واسعة نسبياً\nالمقاسات: XS / S / M / L\nالعناية: غسيل يدوي', 280.00, 320.00, 2, 'p7.svg', 1, 'active', NOW() - INTERVAL 8 DAY),
(8, 5, 'وشاح حرير أمازيغي', 'وشاح حريري بنقوش أمازيغية وألوان ترابية.', 'وشاح حرير أمازيغي بنقوش هندسية مستوحاة من السجاد الأمازيغي. خفيف وواسع، يمكن ارتداؤه بعدة طرق.', 'الخامة: حرير\nالمقاس: 180 × 70 سم\nاللون: طين / رملي\nالعناية: غسيل يدوي بماء بارد', 180.00, NULL, 25, 'p8.svg', 0, 'active', NOW() - INTERVAL 9 DAY),
(9, 5, 'حقيبة جلد مدبوغ', 'حقيبة يد من الجلد الطبيعي المدبوغ يدوياً في مراكش.', 'حقيبة جلد مدبوغ يدوياً في دباغات مراكش العريقة، بتصميم كلاسيكي متين وحزام كتف قابل للتعديل. قطعة تدوم معك سنوات.', 'الخامة: جلد طبيعي مدبوغ\nالمقاس: 30 × 22 × 12 سم\nالبطانة: قطن\nالعناية: تلميع بزيت الجلد', 350.00, 400.00, 10, 'p9.svg', 1, 'active', NOW() - INTERVAL 10 DAY),
(10, 1, 'قفطان سهرة ذهبي', 'قفطان سهرة بتطريز ذهبي كثيف وقماش فاخر، للقطعات الاستثنائية.', 'قفطان سهرة ذهبي مصنوع خصيصاً للمناسبات الاستثنائية. تطريز ذهبي كثيف يدوياً على الصدر والأكمام، مع قماش مبطّن يمنح انسدالاً مثالياً.', 'الخامة: بروكار مبطّن\nالتطريز: ذهبي كثيف يدوي\nالمقاسات: S / M / L\nيتضمن: قفطان + حزام\nالعناية: تنظيف جاف فقط', 1450.00, 1800.00, 3, 'p10.svg', 1, 'active', NOW() - INTERVAL 11 DAY),
(11, 2, 'فستان صيفي نسمة', 'فستان قطني خفيف بنقشة مخططة ناعمة، للصيف والأيام المشمسة.', 'فستان صيفي نسمة من القطن الناعم، بنقشة مخططة هادئة وقصة واسعة مريحة. خفيف على الجسم ومناسب للأجواء الحارة.', 'الخامة: قطن 100%\nالقصة: واسعة\nالمقاسات: S / M / L / XL\nالطول: 115 سم\nالعناية: غسيل آلي على 30 درجة', 390.00, NULL, 18, 'p11.svg', 0, 'active', NOW() - INTERVAL 12 DAY),
(12, 3, 'جلابية رمضان مطرزة', 'جلابية قطنية بتطريز فاخر على الصدر، لسهرات رمضان.', 'جلابية رمضان المطرزة من القطن الفاخر، بتطريز دقيق على الصدر والأكمام وقصة مريحة. قطعة أساسية لسهرات رمضان العائلية.', 'الخامة: قطن فاخر\nالتطريز: يدوي\nالمقاسات: S / M / L / XL\nالعناية: غسيل لطيف', 550.00, 620.00, 9, 'p12.svg', 1, 'active', NOW() - INTERVAL 13 DAY);

-- المقاسات المتاحة لكل منتج
INSERT INTO `product_sizes` (`product_id`, `size_id`) VALUES
(1, 2), (1, 3), (1, 4), (1, 5),
(2, 2), (2, 3), (2, 4), (2, 5), (2, 6),
(3, 1), (3, 2), (3, 3), (3, 4),
(4, 2), (4, 3), (4, 4),
(5, 2), (5, 3), (5, 4), (5, 5),
(6, 2), (6, 3), (6, 4), (6, 5),
(7, 1), (7, 2), (7, 3), (7, 4),
(8, 7),
(9, 7),
(10, 2), (10, 3), (10, 4),
(11, 2), (11, 3), (11, 4), (11, 5),
(12, 2), (12, 3), (12, 4), (12, 5);

-- صور إضافية للمعرض (صور توضيحية قابلة للاستبدال من لوحة التحكم)
INSERT INTO `product_images` (`id`, `product_id`, `image`, `sort_order`) VALUES
(1, 1, 'detail-1.svg', 1), (2, 1, 'detail-2.svg', 2),
(3, 2, 'detail-3.svg', 1),
(4, 3, 'detail-1.svg', 1), (5, 3, 'detail-2.svg', 2), (6, 3, 'detail-3.svg', 3),
(7, 5, 'detail-2.svg', 1),
(8, 10, 'detail-1.svg', 1), (9, 10, 'detail-3.svg', 2);

-- كلمة مرور الحساب: admin123  (يُنصح بتغييرها بعد التثبيت)
-- كلمة مرور العملاء التجريبيين: 123456
INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password`, `address`, `city`, `role`, `created_at`) VALUES
(1, 'مدير المتجر', 'admin@marrakeshia.com', '0600000000', '$2y$12$NZQ2pvQQZcU9OqFylGktJO3mLV0grRVXIGe.SP1iOrXFCyRyxrjiG', 'مراكش، المغرب', 'مراكش', 'admin', NOW() - INTERVAL 30 DAY),
(2, 'سلمى العمراني', 'salma@example.com', '0611223344', '$2y$12$8KbYys8QBzfr2yVnLE7peufgcyRacYhKH78BrzHbTC1p5NKxSYtFa', 'حي جليز، شارع محمد الخامس، رقم 45', 'مراكش', 'customer', NOW() - INTERVAL 20 DAY),
(3, 'ليلى الفاسي', 'leila@example.com', '0655667788', '$2y$12$8KbYys8QBzfr2yVnLE7peufgcyRacYhKH78BrzHbTC1p5NKxSYtFa', 'شارع الحسن الثاني، إقامة النور، الطابق 3', 'فاس', 'customer', NOW() - INTERVAL 15 DAY);

INSERT INTO `orders`
(`id`, `order_number`, `user_id`, `customer_name`, `phone`, `email`, `city`, `address`, `notes`, `subtotal`, `shipping`, `total`, `payment_method`, `status`, `created_at`) VALUES
(1, 'MRK-2026-0001', 2, 'سلمى العمراني', '0611223344', 'salma@example.com', 'مراكش', 'حي جليز، شارع محمد الخامس، رقم 45', 'يرجى التوصيل بعد الخامسة مساءً', 1070.00, 0.00, 1070.00, 'cod', 'delivered', NOW() - INTERVAL 5 DAY),
(2, 'MRK-2026-0002', 3, 'ليلى الفاسي', '0655667788', 'leila@example.com', 'فاس', 'شارع الحسن الثاني، إقامة النور، الطابق 3', NULL, 1000.00, 0.00, 1000.00, 'cod', 'pending', NOW() - INTERVAL 1 DAY),
(3, 'MRK-2026-0003', NULL, 'خديجة بناني', '0677889900', NULL, 'الدار البيضاء', 'حي المعاريف، زنقة 12، رقم 8', 'تغليف هدية من فضلكم', 420.00, 30.00, 450.00, 'cod', 'shipped', NOW() - INTERVAL 2 DAY);

INSERT INTO `order_items` (`order_id`, `product_id`, `product_name`, `price`, `quantity`, `size`) VALUES
(1, 1, 'قفطان مراكش الملكي', 890.00, 1, 'M'),
(1, 8, 'وشاح حرير أمازيغي', 180.00, 1, 'مقاس واحد'),
(2, 3, 'فستان زهرة الصحراء', 720.00, 1, 'S'),
(2, 7, 'بلوزة ياسمين', 280.00, 1, 'M'),
(3, 5, 'جلابية أطلس', 420.00, 1, 'L');

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name', 'مراكشية'),
('tagline', 'أناقة مغربية أصيلة'),
('announcement', 'توصيل مجاني للطلبات فوق 500 د.م. — تغليف هدية مجاناً'),
('phone', '+212 6 12 34 56 78'),
('whatsapp', '212612345678'),
('email', 'hello@marrakeshia.ma'),
('address', 'مراكش، المغرب — حي جليز'),
('instagram', 'https://instagram.com/'),
('facebook', 'https://facebook.com/'),
('shipping_cost', '30'),
('free_shipping_threshold', '500'),
('currency', 'د.م.'),
('hero_image', ''),
('auth_image', ''),
('favicon', ''),
('search_icon', ''),
('payment_methods', 'cod,mada,visa'),
('about_text', 'مراكشية متجر مغربي يحتفي بالأناقة الأصيلة. نختار لك قطعاً مصنوعة بعناية من أقمشة طبيعية وتطريز يدوي، تجمع بين روح مراكش العريقة ولمسة عصرية تليق بك.');

-- ============================================================
--  انتهى
-- ============================================================
