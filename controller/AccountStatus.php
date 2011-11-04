<?php 
/**
* Copyright (C) 2005-2011 BetaCONCEPT LP.
*
* This file is part of Astroboa.
*
* Astroboa is free software: you can redistribute it and/or modify
* it under the terms of the GNU Lesser General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Astroboa is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Lesser General Public License for more details.
*
* You should have received a copy of the GNU Lesser General Public License
* along with Astroboa.  If not, see <http://www.gnu.org/licenses/>.
*
* * @author Gregory Chomatas (gchomatas@betaconcept.com)
*
*/

final class AccountStatus	{
	
	const REGISTERED = 1;
	const NOT_REGISTERED = -1;
	const REGISTERED_BUT_UPDATED_USER_DATA = 2;
	const DUBLICATE_REGISTRATION = -2;
	const REGISTRATION_DATA_CURRENTLY_INACCESSIBLE = 0;
}
?>