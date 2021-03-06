<?php

/**
 * @package StartupAPI
 * @subpackage Subscriptions
 */
require_once(dirname(dirname(__DIR__)) . '/global.php');

UserConfig::$IGNORE_CURRENT_ACCOUNT_PLAN_VERIFICATION = true;

$user = User::require_login();
$account = Account::getCurrentAccount($user);

try {
	// Check if plan and schedule exists
	if (!array_key_exists('plan', $_GET) || !($plan = Plan::getPlanBySlug($_GET['plan']))) {
		throw new Exception("Unknown plan '" . UserTools::escape($_GET['plan']) . '"');
	}

	if (!array_key_exists('schedule', $_GET) || !($schedule = $plan->getPaymentScheduleBySlug($_GET['schedule']))) {
		throw new Exception("Unknown schedule '" . UserTools::escape($_GET['schedule']) . "' for plan '" . UserTools::escape($_GET['plan']) . "'");
	}

	if (!array_key_exists('engine', $_GET) || !($engine = PaymentEngine::getEngineBySlug($_GET['engine']))) {
		throw new Exception("Unknown schedule '" . UserTools::escape($_GET['schedule']) . "' for plan '" . UserTools::escape($_GET['plan']) . "'");
	}
} catch (Exception $e) {
	$_SESSION['message'][] = $e->getMessage();
	header('Location: ' . UserConfig::$USERSROOTURL . '/plans.php?wrongparams');
	exit;
}

if (array_key_exists('paid', $_GET)) {
	// paying enough money to satisfy selected schedule
	$engine->paymentReceived(array('account_id' => $account->getID(), 'amount' => $schedule->getChargeAmount()));

	if ($account->planChangeRequest($plan->getSlug(), $schedule->getSlug(), $engine->getSlug())) {
		header('Location: ' . UserConfig::$DEFAULTLOGINRETURN . '?upgraded');
	} else {
		header('Location: ' . UserConfig::$USERSROOTURL . '/plans.php?failed');
	}
	exit;
}

header('Location: ' . UserConfig::$USERSROOTURL . '/plans.php?notpaid');
