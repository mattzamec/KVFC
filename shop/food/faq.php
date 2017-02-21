<?php
include_once 'includes/config_openfood.php';
session_start();
// valid_auth('member');  // Do not authenticate so this page is accessible to everyone

$active_cycle = new ActiveCycle();
$content_faq = '
  <table width="80%">
    <tr>
      <td align="left">'.$font.'

        <b>Click on the question to see an answer:</b>
        <ul>
          <li> <a href="#order1">How do I order online with this shopping cart?</a>
          <li> <a href="#order2">How do I order <em>not</em> using this shopping cart?</a>
          <li> <a href="#order3">Can I change my order?</a>
          <li> <a href="#order4">When does ordering end for this month?</a>
          <li> <a href="#order5">How can I cancel my order?</a>
          <li> <a href="#pay1">How do I pay?</a>
          <!-- <li> <a href="#pay2">How do I change my payment method?</a> -->
          <li> <a href="#del1">When can I pick up my order?</a>
          <li> <a href="#del2">Can I have my order delivered?</a>
          <!-- <li> <a href="#del3">Can I change my delivery method once I have chosen it?</a> -->
          <!-- <li> <a href="#prdcr1">I am a producer, where do I send my product updates?</a> -->
          <li> <a href="#web1">I am getting an error on a page, what do I do?</a>
          <li> <a href="#web2">I have a suggestion on how to make this website easier to use.</a>
          <!-- <li> <a href="member_form.php">How do I update my contact information?</a> -->
          <li> <a href="#q1">What if I have questions that are not covered in this list?</a>
        </ul>
        <div id="order1"></div>
        <b>Q: How do I order online with this shopping cart?</b>
        <br/>
        <b>A:</b> The member log-in page is <a href="'.PATH.'">'.PATH.'</a>. If an order is open for shopping, you can open a basket by selecting a link on the <a href="'.PATH.'panel_shopping.php">Shopping Panel</a> page. You can place both regular weekly orders, and monthly Bulk Item orders for larger amounts of dry goods. The process for regular and bulk ordering is the same:
        <ol>
        <li>You can browse through the product lists and click "Add to Shopping Cart". When you do this, the system adds one of the items you have selected to your cart. You can adjust the quantity in your basket by pressing the +/- buttons near the basket icon. If you need to add notes to the producer, such as "red, medium sized tomatoes" or "make this a small pig", click on View Your Cart, place the cursor in the box for notes for that product, type in the notes, and then press the &quot;Update Message&quot; button to the right of the entry form. When you are done, there is no need to submit your order - whatever remains in your basket when the order closes will be considered your order. <br/>
        <br/>
        <li>To remove a product from your shopping cart, press the - (minus) button near the basket icon until the quantity is reduced to zero.<br/>
        <br/>
        <li>You can edit your order up until the time that the Order Desk closes at the end of Delivery Day. The time of closing is announced at the beginning of Order Week. To edit your order (add or subtract items, change quantities, add notes), log in at <a href="'.PATH.'">'.PATH.'</a> . There is no need to submit your order - whatever remains in your basket when the order closes will be considered your order.
        </ol>
        <!-- Note: the shopping cart will show a subtotal, but it will not necessarily subtotal everything you have ordered, as items with random weights (such as packages of meat or cheese) will not be totaled until that information is updated from the producers. -->
        <div id="order2"></div>
        <b>Q: How do I order <em>not</em> using this shopping cart?</b>
        <br/>
        <b>A:</b> For ordering <em>not</em> using this online shopping cart,
        please email <a href="mailto:'.CUSTOMER_EMAIL.'">'.CUSTOMER_EMAIL.'</a> to ask if there is a &quot;computer buddy&quot; who can take your order by phone or fax.
        <p/>
        <div id="order3"></div>
        <b>Q: Can I change my order?</b>
        <br/>
        <b>A:</b> You can log in and change your order until <strong>'.date ('g:i a, F j', strtotime ($active_cycle->date_closed())).'</strong>. Between then and the delivery day, producers will be entering weights on any items that need it and putting your order together. You can view your temporary invoice in progress during that time by logging in.
        <p/>
        <div id="order4"></div>
        <b>Q: When does ordering end for this month?</b>
        <br/>
        <b>A:</b> You can log in and change your order until <strong>'.date ('g:i a, F j', strtotime ($active_cycle->date_closed())).'</strong>.
        <p/>
        <div id="order5"></div>
        <b>Q: How can I cancel my order?</b>
        <br/>
        <b>A:</b> To cancel your order, you must change the quantity for each item in your shopping cart to zero.  Any items that remain in the shopping cart when the order closes will be considered a valid order and you will be expected to pay for them.
        <p/>
        <div id="pay1"></div>
        <b>Q: How do I pay?</b>
        <br/>
        <b>A:</b> You will receive a paper copy of your invoice with your order on delivery day with the final total owed. Payment is due at pickup. You can pay by cash or write a cheque to Kettle Valley Food Coop and pay when picking up your order.
<!--        <div id="pay2"></div>
        <b>Q: How do I change my payment method?</b>
        <br/>
        <b>A:</b> To change how you will pay, once your invoice is finalized after delivery day, you will be shown totals for different methods of payment. You can then decide to write a check, or log on and pay by PayPal online. You will also be able to change your method of payment at that time. If you have questions about this at that time, contact us at <a href="mailto:'.HELP_EMAIL.'">'.HELP_EMAIL.'</a> -->
        <p/>
        <div id="del1"></div>
        <b>Q: When can I pick up my order?</b>
        <br/>
        <b>A:</b> Delivery Day is <strong>'.date ('F j', strtotime ($active_cycle->delivery_date())).'</strong>. Your temporary invoice (viewable after ordering is closed) will have the information on pick up location.
        <p/>
        <div id="del2"></div>
        <b>Q: Can I change my delivery method once I have chosen it?</b>
        <br/>
        <b>A:</b> Contact us at <a href="mailto:'.ORDER_EMAIL.'">'.ORDER_EMAIL.'</a> to change your delivery method.
        <p/>
        <div id="del2"></div>
        <b>Q: Can I have my order delivered?</b>
        <br/>
        <b>A:</b> At this point, order have to be picked up in person on Delivery Day. If you have special needs and need to coordinate a pickup, please contact us at <a href="mailto:'.CUSTOMER_EMAIL.'">'.CUSTOMER_EMAIL.'</a> and we will gladly assist you.
        <p/>
        <div id="web1"></div>
        <b>Q: I am getting an error on a page, what do I do?</b>
        <br/>
        <b>A:</b> Please copy and paste the text of the error into an email along with what page it is and send it to <a href="mailto:'.WEBMASTER_EMAIL.'">'.WEBMASTER_EMAIL.'</a>. Please also explain what happened before that error occurred. Thank you for your help in keeping this website working smoothly.
        <p/>
        <div id="web2"></div>
        <b>Q: I have a suggestion on how to make this website easier to use.</b>
        <br/>
        <b>A:</b> Please send your suggestions to <a href="mailto:'.WEBMASTER_EMAIL.'">'.WEBMASTER_EMAIL.'</a>. Thank you for your help in keeping this website working smoothly.
        <p/>
        <div id="q1"></div>
        <b>Q: What if I have questions that are not covered in this list?</b>
        <br/>
        <b>A:</b> You can contact the appropriate person by looking on the <a href="contact.php">Contact Us</a> page.
      </td>
    </tr>
  </table>';

$page_title_html = '<span class="title">Member Resources</span>';
$page_subtitle_html = '<span class="subtitle">How to Order FAQ</span>';
$page_title = 'Member Resources: How to Order FAQ';
$page_tab = 'member_panel';

include("template_header.php");
echo '
  <!-- CONTENT BEGINS HERE -->
  '.$content_faq.'
  <!-- CONTENT ENDS HERE -->';
include("template_footer.php");