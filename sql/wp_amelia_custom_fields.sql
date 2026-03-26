-- rewind begin page null
CREATE TABLE `wp_amelia_custom_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `label` text DEFAULT NULL,
  `type` enum('text','text-area','select','checkbox','radio','content','file','datepicker','address') NOT NULL DEFAULT 'text',
  `required` tinyint(1) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL,
  `translations` text DEFAULT NULL,
  `allServices` tinyint(1) DEFAULT NULL,
  `allEvents` tinyint(1) DEFAULT NULL,
  `useAsLocation` tinyint(1) DEFAULT NULL,
  `width` int(11) NOT NULL DEFAULT 50,
  `saveType` enum('bookings','customer') NOT NULL DEFAULT 'bookings',
  `saveFirstChoice` tinyint(1) DEFAULT NULL,
  `includeInInvoice` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
-- rewind end page null
