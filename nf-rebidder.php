<?php

/* Get saved configuration */
$config = [
	"listing" => "https://www.niteflirt.com/account/featured_listings/123456-some-listing-name-is-here?category_id=789",
	"login_name" => "",
	"login_password" => "",
	"position" => 5, /* 1-12 gets you on the first page */
	"highbid" => true,
	"debug" => true,
];

/* Get the configured page */
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $config["listing"]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_COOKIEFILE, "nf-rebidder.txt");
curl_setopt($ch, CURLOPT_COOKIEJAR, "nf-rebidder.txt");
$output = curl_exec($ch);

/* See if we got redirected to log in */
/* 301: <html><body>You are being <a href="https://www.niteflirt.com/login">redirected</a>.</body></html>*/
if ((strpos($output, "niteflirt.com/login") !== false) && (strpos($output, "redirected") !== false))
{
	/* Perform Log in. If it's successful, continue with the script */
	if ($config["debug"])
	{
		echo date(DATE_RFC2822) . ": logging in\n";
	}
	curl_setopt($ch, CURLOPT_URL, "https://www.niteflirt.com/login");
	$output = curl_exec($ch);

	$form_post = [
		"utf8" => "&#x2713;",
		"authenticity_token" => "",
		"return_to" => "",
		"return_url" => "",
		"extra" => "",
		"login" => "",
		"password" => "",
		"commit" => "Sign In &gt;",
	];
	/* Just need authenticity_token from the form */
	if (preg_match('/<input\stype="hidden"\sname="authenticity_token"\svalue="(.*?)"\s\/>/', $output, $matches) === 1)
	{
		$form_post["authenticity_token"] = $matches[1];
	}
	$form_post["login"] = $config["login_name"];
	$form_post["password"] = $config["login_password"];
	/* Hook up cURL to send the log in */
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_URL, "https://www.niteflirt.com/login");
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form_post));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	$output = curl_exec($ch);

	if (strpos($output, "incorrect email address") !== false)
	{
		/* Failed: We're sorry, but you've entered an incorrect email address, member name or password. Please try again. */
		die("login failed, bad username or password");
	}
	if ($config["debug"])
	{
		echo date(DATE_RFC2822) . ": login successful\n";
	}
}

/* Set up some variables */
$bids = []; /* A current list of the top 20 bids */
$my_position = 0; /* If we detect your current position, it'll be here */
$current_bid = 0; /* How much our current bid is */
$balance = 0; /* How much our detected balance is */

/* Parse out the page */
/* Get the balance into $balance */
if (preg_match('/Your\scurrent\sbalance\sis\s\$([\d\.]+)</', $output, $matches) === 1)
{
	$balance = $matches[1];
}
else
{
	die("Balance is not detected on current page");
}

/* Get all bids into $bid */
if (preg_match_all('/<td\sclass="bid">(?:[\w\s()]+?)?\$([\d\.]+)<\/td>/', $output, $matches) > 0)
{
	if ($config["debug"]) { echo date(DATE_RFC2822) . ": Got bid list on page 1\n"; }
	$bids = $matches[1];
	foreach ($matches[0] as $idx => $is_current_bid)
	{
		if (strpos($is_current_bid, "Current Bid") !== false)
		{
			if ($config["debug"])
			{
				echo date(DATE_RFC2822) . ": I see our current bid and position\n";
			}
			$my_position = $idx + 1; /* arrays are 0-based, we need exact position */
			$current_bid = $bids[$idx]; /* If we found our bid, save it */
			break;
		}
	}
}

if ($config["debug"])
{
	echo date(DATE_RFC2822) . ": page details\n";
	echo date(DATE_RFC2822) . ": \$bids: " . implode(",", $bids) . "\n";
	echo date(DATE_RFC2822) . ": \$my_position: " . $my_position . "\n";
	echo date(DATE_RFC2822) . ": \$current_bid: " . $current_bid . "\n";
	echo date(DATE_RFC2822) . ": \$balance: " . $balance . "\n";
}

/* If we are currently in our position or in a better position, we're good. */
if (($my_position > 0) && ($my_position <= $config["position"]))
{
	if ($config["debug"])
	{
		echo date(DATE_RFC2822) . ": We are where we want to be\n";
	}
	die();
}

/* If we want first, we're just gonna bid a penny more than 1st place. */
if ($config["position"] == 1)
{
	$bid_amt = $bids[0] + 0.01;
}
else
{
	if ($config["highbid"])
	{
		/**
		 * To "be" in that position we have to bid more. We're gonna bid 1 penny less than the next to keep our position longer.
		 * So, for 2nd place, we need the price of 1st place (at index 0) and we subtract 1 penny from that to stick us there.
		 * This approach is more stable and requires less bidding.
		 */
		$bid_amt = $bids[$config["position"] - 2] - 0.01;
	}
	else
	{
		/* At this point we butt up again next place. This approach is more volatile (like 1st place) and would require more shifts */
		$bid_amt = $bids[$config["position"] - 1] + 0.01;
	}
}
if ($config["debug"])
{
	echo date(DATE_RFC2822) . ": For position " . $config["position"] . " we have to bid $bid_amt\n";
}

if ($bid_amt > $balance)
{
	echo date(DATE_RFC2822) . ": We cannot set requested bid since balance < bid (\$$balance < \$$bid_amt)\n";
	die();
}

/* Lets tell NF that we want our new bid to be done */
$form_post = [
	"utf8" => "&#x2713;",
	"_method" => "put",
	"authenticity_token" => "",
	"category_id" => "",
	"bid_strategy[max_bid]" => $bid_amt,
	"bid_expiration" => "unlimited_spend",
	"bid_strategy[budget]" => "",
	"commit" => "Continue",
];
if (preg_match('/<input\stype="hidden"\sname="authenticity_token"\svalue="(.*?)"\s\/>/', $output, $matches) === 1)
{
	$form_post["authenticity_token"] = $matches[1];
}
if (preg_match('/<input\stype="hidden"\sname="category_id"\sid="category_id"\svalue="(.*?)"\s\/>/', $output, $matches) === 1)
{
	$form_post["category_id"] = $matches[1];
}

/* Only thing that is totally special to this */
$post_action = "";
if (preg_match('/<form[\w\s="]+action="(.*?)"/', $output, $matches) === 1)
{
	$post_action = "https://www.niteflirt.com" . $matches[1];
}
else
{
	die("fatal error, we cannot find POST action on bid screen.");
}

if ($config["debug"])
{
	echo date(DATE_RFC2822) . ": Bidding / ready to POST to $post_action\n";
}
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_URL, $post_action);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form_post));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$output = curl_exec($ch);

/* Confirm the bid */
$form_post = [
	"utf8" => "&#x2713;",
	"_method" => "put",
	"authenticity_token" => "",
	"bid_strategy[max_bid]" => $bid_amt,
	"bid_strategy[budget]" => "",
	"confirm[confirm]" => "",
	"commit" => "Confirm Bid",
];
if (preg_match('/<input\stype="hidden"\sname="authenticity_token"\svalue="(.*?)"\s\/>/', $output, $matches) === 1)
{
	$form_post["authenticity_token"] = $matches[1];
}
if ($config["debug"])
{
	echo date(DATE_RFC2822) . ": Confirming / ready to POST to $post_action\n";
}
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_URL, $post_action);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form_post));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$output = curl_exec($ch);

if (strpos($output, "Congratulations! You have successfully") != false)
{
	if ($config["debug"])
	{
		echo date(DATE_RFC2822) . ": We are Congratulated\n";
	}
}
else
{
	if ($config["debug"])
	{
		echo date(DATE_RFC2822) . ": We are confused\n";
	}
}

/* All done */
curl_close($ch);
if ($config["debug"])
{
	echo date(DATE_RFC2822) . ": All Done!\n";
}
