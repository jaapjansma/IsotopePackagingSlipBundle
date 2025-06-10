<?php
/**
 * Copyright (C) 2022  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Krabo\IsotopePackagingSlipBundle\Event;

class Events {

  const STATUS_CHANGED_EVENT = 'krabo.isotope_packaging_slip.status_changed';

  const PACKAGING_SLIP_CREATED_FROM_ORDER = 'krabo.isotope_packaging_slip.created_from_order';

  const PACKAGING_SLIP_PRODUCTS_FROM_ORDER = 'krabo.isotope_packaging_slip.products_from_order';

  const GENERATE_ADDRESS = 'krabo.isotope_packaging_slip.generate_address';

  const GENERATE_TRACKTRACE_TOKEN = 'krabo.isotope_packaging_slip.generate_tracktrace_token';

  const CHECK_AVAILABILITY = 'krabo.isotope_packaging_slip.check_availability';

}