<!--
/*
 * common.js, 06 octobre 2007
 * 
 * This file is part of Galette.
 *
 * Copyright © 2007 Johan Cwiklinski
 *
 * File :               	common.js
 * Author's email :     	johan@x-tnd.be
 * Author's Website :   	http://galette.tuxfamily.org
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 * 
 */
-->

/* On document ready
-------------------------------------------------------- */
$(function() {
	/**
	* Errorbox animation
	*/
	$('#errorbox').backgroundFade({sColor:'#ffffff',eColor:'#ff9999',steps:50},function() {
		$(this).backgroundFade({sColor:'#ff9999',eColor:'#ffffff'});
	});

	/**
	* Let's round some corners !
	*/
	Nifty('#menu');
	Nifty('#listfilter');
	Nifty('#titre');

});