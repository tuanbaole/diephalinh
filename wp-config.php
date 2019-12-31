<?php
/**
 * Cấu hình cơ bản cho WordPress
 *
 * Trong quá trình cài đặt, file "wp-config.php" sẽ được tạo dựa trên nội dung 
 * mẫu của file này. Bạn không bắt buộc phải sử dụng giao diện web để cài đặt, 
 * chỉ cần lưu file này lại với tên "wp-config.php" và điền các thông tin cần thiết.
 *
 * File này chứa các thiết lập sau:
 *
 * * Thiết lập MySQL
 * * Các khóa bí mật
 * * Tiền tố cho các bảng database
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** Thiết lập MySQL - Bạn có thể lấy các thông tin này từ host/server ** //
/** Tên database MySQL */
define('WP_CACHE', true);
define( 'WPCACHEHOME', '/home/diephali/public_html/wp-content/plugins/wp-super-cache/' );
define( 'DB_NAME', 'diephali_db' );

/** Username của database */
define( 'DB_USER', 'diephali_root' );

/** Mật khẩu của database */
define( 'DB_PASSWORD', 'Anhminh@123' );

/** Hostname của database */
define( 'DB_HOST', 'localhost' );

/** Database charset sử dụng để tạo bảng database. */
define( 'DB_CHARSET', 'utf8mb4' );

/** Kiểu database collate. Đừng thay đổi nếu không hiểu rõ. */
define('DB_COLLATE', '');

/**#@+
 * Khóa xác thực và salt.
 *
 * Thay đổi các giá trị dưới đây thành các khóa không trùng nhau!
 * Bạn có thể tạo ra các khóa này bằng công cụ
 * {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * Bạn có thể thay đổi chúng bất cứ lúc nào để vô hiệu hóa tất cả
 * các cookie hiện có. Điều này sẽ buộc tất cả người dùng phải đăng nhập lại.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'YufX_>ukc^A-b0_xP1V8Jp`uNv`xQt.hJo<O*F4)g3b903<BdvbLi;CbSS+;qh/?' );
define( 'SECURE_AUTH_KEY',  '0%itM:xdsxO+2]2Iy_^s+GD4)tl4iY#6p@w~rJQb8dCM/XQ?ly-R3]8aN4.ngu$d' );
define( 'LOGGED_IN_KEY',    ',1|?A>Ph(Iu^Gi?6 6qae<4xp.SrX!E$d7$eA %|d}_zHh(3etwz85X{ocs:W[#y' );
define( 'NONCE_KEY',        '4uj0[lB !>+p![)}X[1-%|$N5X.&eR0O_|hg!2M 4f.s#]ebIzMU-+<G[;%(b3cp' );
define( 'AUTH_SALT',        ':Y|.Lw_1rb3q?3~DtIl#^.-{8G7zN~~S{bf_$z:qpYYieUHt!DLyC[Y2%t~qHtA+' );
define( 'SECURE_AUTH_SALT', '(pJt$l+)^$P]ku&~#MT &;C{>H}EV,|V?K9SVf:{9T.70K%w&o73pzcHNLe5Q/,F' );
define( 'LOGGED_IN_SALT',   '=d~:8_e2k>Pf>q.0[$>KU1l~=m+AD#$q.]zOzTH/?6t`4,ujsmVTWPSh:V/j:dX)' );
define( 'NONCE_SALT',       'r<h{J<+EQy#QC6i0%:#6M%?!()y[mS5:W67u&j4_0M_3[*/Dh2L9*q1@)^Rs.#J6' );

/**#@-*/

/**
 * Tiền tố cho bảng database.
 *
 * Đặt tiền tố cho bảng giúp bạn có thể cài nhiều site WordPress vào cùng một database.
 * Chỉ sử dụng số, ký tự và dấu gạch dưới!
 */
$table_prefix  = 'wp_';

/**
 * Dành cho developer: Chế độ debug.
 *
 * Thay đổi hằng số này thành true sẽ làm hiện lên các thông báo trong quá trình phát triển.
 * Chúng tôi khuyến cáo các developer sử dụng WP_DEBUG trong quá trình phát triển plugin và theme.
 *
 * Để có thông tin về các hằng số khác có thể sử dụng khi debug, hãy xem tại Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* Đó là tất cả thiết lập, ngưng sửa từ phần này trở xuống. Chúc bạn viết blog vui vẻ. */

/** Đường dẫn tuyệt đối đến thư mục cài đặt WordPress. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Thiết lập biến và include file. */
require_once(ABSPATH . 'wp-settings.php');
