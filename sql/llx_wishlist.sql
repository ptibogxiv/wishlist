-- ============================================================================
-- Copyright (C) 2020	 Thibault FOUCART 	 <support@ptibogxiv.net>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ============================================================================


CREATE TABLE llx_wishlist (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,         -- multi company id
  tms timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  fk_soc integer NOT NULL,
  fk_product integer DEFAULT NULL,
  label text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  description text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  fk_remise_except integer DEFAULT NULL,
  qty double NOT NULL,
  target double NOT NULL,
  remise_percent double DEFAULT '0',
  subprice double(24,8) DEFAULT '0.00000000',
  remise double DEFAULT '0',
  product_type integer DEFAULT '1',
  datec datetime DEFAULT NULL,
  info_bits integer DEFAULT '0',
  fk_product_fournisseur_price integer DEFAULT NULL,
  rang integer NOT NULL DEFAULT '0',
  fk_unit integer DEFAULT NULL,
  priv smallint(6) NOT NULL DEFAULT '0',
  fk_user_author integer DEFAULT NULL,
  fk_user_mod integer DEFAULT NULL
)ENGINE=InnoDB;