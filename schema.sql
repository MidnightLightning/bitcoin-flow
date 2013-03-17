CREATE TABLE `txn` (
  `index` int(10) unsigned DEFAULT NULL,
  `hash` varchar(100) DEFAULT NULL,
  `cache_time` timestamp NULL DEFAULT NULL,
  `data` text,
  UNIQUE KEY `hash_UNIQUE` (`hash`),
  UNIQUE KEY `index_UNIQUE` (`index`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1
