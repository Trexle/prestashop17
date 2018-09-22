{*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

<!-- <div style="margin-left: 2.875rem;"> -->
<div class="trexle" id="trexle-block">

<form action="{$form_action}" id="payment-form">

    <div>
    <label>{l s='Credit Card Number' mod='pinpayments'}</label>
    <input type="text" size="20" autocomplete="off" name="cc_number" id="cc_number" >
    </div>

 <!-- Date Field -->


    <div id="date_field"    style="margin-top: 15px;">
           <label>{l s='Expiration' mod='trexle'}</label>
      <div id="month" style="float: left">
         <select name="cc_month">

      {foreach from=$months item=month}
        <option value="{$month}">{$month}</option>
      {/foreach}
    </select>
    </div>
     <span style="float: left"> &nbsp; / &nbsp;</span>
     <div id="year" style="float: left">
    <select name="cc_year">

      {foreach from=$years item=year}
        <option value="{$year}">{$year}</option>
      {/foreach}
    </select>
  </div>
  </div>

    <!-- Card Verification Field -->

    <div class="card-verification" style="clear: both;  margin-top: 40px;" >
          <label>{l s='CV Code' mod='trexle'}</label>
      <div id="cvc_input">
        <input type="text" name="cc_cvv" size="4" autocomplete="off" style="float: left">
         <p style="margin-left: 70px" >{l s='3 or 4 digits usually found on the signature strip'  mod='trexle'} </p>
      </div>

</div>


</form>
</div>
