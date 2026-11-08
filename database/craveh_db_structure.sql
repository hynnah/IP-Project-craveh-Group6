-- phpMyAdmin SQL Dump
-- version 5.2.2deb1+deb13u1
-- https://www.phpmyadmin.net/
--
-- Server version: 11.8.6-MariaDB-0+deb13u1 from Debian
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `craveh_group6` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `craveh_group6`;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `craveh_group6` (local XAMPP). Structure + menu_items only. NO user/order/cart data.
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics`
--

CREATE TABLE `analytics` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `page_url` varchar(500) NOT NULL,
  `page_type` enum('menu','checkout','history','account','other') DEFAULT 'other',
  `referrer` varchar(500) DEFAULT NULL,
  `visit_time` timestamp NULL DEFAULT current_timestamp(),
  `time_on_page` int(11) DEFAULT 0,
  `last_page` varchar(500) DEFAULT NULL,
  `menu_items_viewed` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`menu_items_viewed`)),
  `menu_items_added_to_cart` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`menu_items_added_to_cart`)),
  `search_query` varchar(255) DEFAULT NULL,
  `category_filter` varchar(100) DEFAULT NULL,
  `cart_value` decimal(10,2) DEFAULT NULL,
  `checkout_completed` tinyint(1) DEFAULT 0,
  `order_id` int(11) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_orders`
--

CREATE TABLE `client_orders` (
  `id` int(11) NOT NULL,
  `client_user_id` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_address` text NOT NULL,
  `phone` varchar(50) NOT NULL,
  `items` text NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `name`, `description`, `price`, `image_url`, `category`, `is_available`, `created_at`, `updated_at`) VALUES
(1, 'Classic Burger', 'Juicy beef patty with lettuce, tomato, and special sauce', 500.00, 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&h=300&fit=crop', 'Burgers', 1, '2026-04-29 14:44:27', '2026-05-14 09:40:40'),
(3, 'Caesar Salad', 'Crisp romaine lettuce with parmesan and croutons', 89.00, 'https://images.unsplash.com/photo-1546793665-c74683f339c1?w=400&h=300&fit=crop', 'Salads', 1, '2026-04-29 14:44:27', '2026-05-14 08:02:11'),
(4, 'Chicken Wings', '8pcs crispy wings with your choice of sauce yum', 500.00, '../uploads/menu/menu_6a03f9c226deb.jpg', 'Appetizers', 1, '2026-04-29 14:44:27', '2026-05-14 09:40:10'),
(5, 'Spaghetti Carbonara', 'Creamy pasta with bacon and parmesan', 139.00, 'https://images.unsplash.com/photo-1612874742237-6526221588e3?w=400&h=300&fit=crop', 'Pasta', 1, '2026-04-29 14:44:27', '2026-05-12 17:02:11'),
(6, 'Fish & Chips', 'Crispy battered fish with golden fries', 149.00, '../uploads/menu/menu_69f9f50573482.jpg', 'Seafood', 1, '2026-04-29 14:44:27', '2026-05-12 17:03:01'),
(7, 'BBQ Ribs', 'Tender ribs with smoky BBQ sauce', 299.00, 'https://images.unsplash.com/photo-1544025162-d76694265947?w=400&h=300&fit=crop', 'BBQ', 1, '2026-04-29 14:44:27', '2026-05-12 17:00:15'),
(8, 'Tacos', 'Three soft tacos with your choice of protein', 109.00, 'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=400&h=300&fit=crop', 'Mexican', 1, '2026-04-29 14:44:27', '2026-05-12 17:01:42'),
(9, 'Pepperoni Pizza', 'Classic pepperoni with mozzarella cheese', 299.00, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=400&h=300&fit=crop', 'Pizza', 1, '2026-04-29 14:44:27', '2026-05-12 17:02:33'),
(10, 'Grilled Salmon', 'Fresh Atlantic salmon with herbs', 269.00, 'https://images.unsplash.com/photo-1485921325833-c519f76c4927?w=400&h=300&fit=crop', 'Seafood', 1, '2026-04-29 14:44:27', '2026-05-12 17:03:12'),
(11, 'Chicken Alfredo', 'Fettuccine pasta with creamy alfredo sauce', 149.00, 'https://images.unsplash.com/photo-1645112411341-6c4fd023714a?w=400&h=300&fit=crop', 'Pasta', 1, '2026-04-29 14:44:27', '2026-05-12 17:02:00'),
(12, 'Greek Salad', 'Fresh vegetables with feta cheese and olives', 99.00, 'https://images.unsplash.com/photo-1540189549336-e6e99c3679fe?w=400&h=300&fit=crop', 'Salads', 1, '2026-04-29 14:44:27', '2026-05-12 17:02:50'),
(13, 'Cheeseburger', 'Double beef patty with melted cheddar cheese', 149.00, 'https://images.unsplash.com/photo-1572802419224-296b0aeee0d9?w=400&h=300&fit=crop', 'Burgers', 1, '2026-04-29 14:44:27', '2026-05-12 17:00:27'),
(15, 'Mozzarella Sticks', 'Crispy breaded mozzarella with marinara', 79.00, 'https://images.unsplash.com/photo-1531749668029-2db88e4276c7?w=400&h=300&fit=crop', 'Appetizers', 1, '2026-04-29 14:44:27', '2026-05-14 08:03:10'),
(16, 'Beef Burrito', 'Large flour tortilla filled with seasoned beef', 129.00, 'https://images.unsplash.com/photo-1626700051175-6818013e1d4f?w=400&h=300&fit=crop', 'Mexican', 1, '2026-04-29 14:44:27', '2026-05-12 17:01:30'),
(17, 'Iced Tea', 'Chilled black tea lightly sweetened and served over ice', 65.00, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=400&h=300&fit=crop', 'Beverages', 1, '2026-05-12 17:04:01', '2026-05-12 17:04:01'),
(18, 'Brownie Sundae', 'Warm fudgy brownie topped with a scoop of vanilla ice cream and chocolate drizzle', 99.00, 'https://images.unsplash.com/photo-1606313564200-e75d5e30476c?w=400&h=300&fit=crop', 'Desserts', 1, '2026-05-12 17:04:49', '2026-05-12 17:04:49'),
(19, 'New York Cheesecake', 'Classic creamy cheesecake on a buttery graham cracker crust', 89.00, 'https://images.unsplash.com/photo-1533134242443-d4fd215305ad?w=400&h=300&fit=crop', 'Desserts', 1, '2026-05-12 17:06:47', '2026-05-12 17:06:47'),
(20, 'Chocolate Lava Cake', 'Warm chocolate cake with a gooey molten center, served with vanilla ice cream', 99.00, 'https://images.unsplash.com/photo-1563805042-7684c019e1cb?w=400&h=300&fit=crop', 'Desserts', 1, '2026-05-12 17:07:42', '2026-05-12 17:12:05'),
(21, 'Vanilla Ice Cream', 'Two scoops of rich creamy vanilla bean ice cream', 59.00, 'https://images.unsplash.com/photo-1570197788417-0e82375c9371?w=400&h=300&fit=crop', 'Desserts', 1, '2026-05-12 17:08:25', '2026-05-12 17:12:20'),
(23, 'Fresh Lemonade', 'Freshly squeezed lemon juice with a hint of mint', 55.00, 'https://images.unsplash.com/photo-1621263764928-df1444c5e859?w=400&h=300&fit=crop', 'Beverages', 1, '2026-05-12 17:10:07', '2026-05-12 17:12:51'),
(24, 'Iced Coffee', 'Cold-brewed coffee over ice, served with milk', 45.00, 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?w=400&h=300&fit=crop', 'Beverages', 1, '2026-05-12 17:10:47', '2026-05-12 17:13:02'),
(25, 'Chocolate Milkshake', 'Thick and creamy chocolate milkshake topped with whipped cream', 85.00, 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?w=400&h=300&fit=crop', 'Beverages', 1, '2026-05-12 17:11:18', '2026-05-12 17:12:42'),
(30, 'Kinilaw', 'Diced raw fish mixed with vinegar, onions, sili ispada topped with basil.', 299.00, '../uploads/menu/menu_6a055ecf170b8.jpg', 'Seafood', 1, '2026-05-14 05:34:07', '2026-05-14 05:34:07'),
(31, 'Margherita Pizza', 'Fresh mozzarella, basil, and tomato sauce', 279.00, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=400&h=300&fit=crop', 'Pizza', 1, '2026-05-14 08:17:27', '2026-05-14 08:19:00');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_address` text NOT NULL,
  `phone` varchar(50) NOT NULL,
  `status` enum('pending','confirmed','preparing','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `menu_item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price_at_purchase` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `role` enum('admin','customer') DEFAULT 'customer',
  `delivery_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`delivery_address`)),
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_carts`
--

CREATE TABLE `user_carts` (
  `user_id` int(11) NOT NULL,
  `cart_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`cart_data`)),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics`
--
ALTER TABLE `analytics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_visit_time` (`visit_time`),
  ADD KEY `idx_page_type` (`page_type`);

--
-- Indexes for table `client_orders`
--
ALTER TABLE `client_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_available` (`is_available`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_menu_item_id` (`menu_item_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `user_carts`
--
ALTER TABLE `user_carts`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics`
--
ALTER TABLE `analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_orders`
--
ALTER TABLE `client_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

--
-- Constraints for table `user_carts`
--
ALTER TABLE `user_carts`
  ADD CONSTRAINT `user_carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
