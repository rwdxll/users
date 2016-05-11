<?php
namespace StartupAPI;

require_once(dirname(__DIR__) . '/twig/lib/Twig/Autoloader.php');
\Twig_Autoloader::register();

/**
 * StartupAPI class contains some global static functions and entry points for API
 *
 * @package StartupAPI
 */
class StartupAPI {

	/**
	 * @var int Startup API major version number - to be changed only manually in this code
	 */
	private static $major_version = 0;

	/**
	 * @var int Startup API minor version - to be incremented automatically when asked for
	 */
	private static $minor_version = 9;

	/**
	 * @var int	Startup API patch level (version number) - to be incremented automatically when build script is ran
	 */
	private static $patch_level = 0;

	/**
	 * @var string Startup API pre-release version string
	 */
	private static $pre_release_version = 'dev';

	/**
	 * @var string Startup API build version string
	 */
	private static $build_version;

	/**
	 * @var Twig_Environment Templating tool to use for rendering templates
	 */
	public static $template;

	/**
	 * Just a proxy to static User::get() method in User class
	 *
	 * @return User|null
	 */
	static function getUser() {
		return User::get();
	}

	/**
	 * Just a proxy to static User::require_login() method in User class
	 *
	 * @return User
	 */
	static function requireLogin() {
		return User::require_login();
	}

	/**
	 * This finction should be called within the head of HTML to insert
	 * styles, scripts and potentially meta-tags into the head of the pages on the site
	 */
	static function head() {
		echo self::renderHeadHTML();
	}

	/**
	 * @return string HTML to be output withing <head> tag on the page
	 */
	static function renderHeadHTML() {
		return StartupAPI::$template->render('@startupapi/head_tag.html.twig', self::getTemplateInfo());
	}

	/**
	 * This finction renders the power strip (navigation bar at the top right corner)
	 */
	static function power_strip($nav_pills = null, $show_navbar = null, $inverted_navbar = null, $pull_right = null) {
		echo self::renderPowerStrip($nav_pills, $show_navbar, $inverted_navbar, $pull_right);
	}

	static function renderPowerStrip($nav_pills = null, $show_navbar = null, $inverted_navbar = null, $pull_right = null) {
		$template_info = array_merge(self::getTemplateInfo(), array(
			'POWERSTRIP' => array(
				'nav_pills' => is_null($nav_pills) ? UserConfig::$powerStripNavPills : $nav_pills,
				'show_navbar' => is_null($show_navbar) ? UserConfig::$powerStripShowNavbar : $show_navbar,
				'inverted_navbar' => is_null($inverted_navbar) ? UserConfig::$powerStripInvertedNavbar : $inverted_navbar,
				'pull_right' => is_null($pull_right) ? UserConfig::$powerStripPullRight : $pull_right
			))
		);

		return StartupAPI::$template->render('@startupapi/power_strip.html.twig', $template_info);
	}

	/**
	 * Incrememts minor version of software
	 */
	public static function incrementMinorVersion() {
		self::$minor_version++;
	}

	/**
	 * Incrememts patch level of software
	 */
	public static function incrementPatchLevel() {
		self::$patch_level++;
	}

	/**
	 * Returns a string representing Statup API version
	 *
	 * @return string Startup API version
	 */
	public static function getVersion() {
		$version = self::$major_version . '.' . self::$minor_version . '.' . self::$patch_level;

		if (!is_null(self::$pre_release_version)) {
			$version .= '-' . self::$pre_release_version;
		}

		if (!is_null(self::$build_version)) {
			$version .= '+build.' . self::$build_version;
		}

		return $version;
	}

	/**
	 * This function is called after all configuration is loaded to initialize the system.
	 */
	static function init() {
		/**
		 * Legacy configuration options support
		 */
		if (!is_null(UserConfig::$enableInvitations)) {
			UserConfig::$adminInvitationOnly = UserConfig::$enableInvitations;
			error_log('[Deprecated] You are using deprecated configuration setting: UserConfig::$enableInvitations - rename it to UserConfig::$adminInvitationOnly');
		}

		if (!is_null(UserConfig::$appName)) {
			UserConfig::$supportEmailXMailer = UserConfig::$appName . ' using ' . UserConfig::$supportEmailXMailer;
		}

		// Initializing more structures based on user configurations
		Plan::init(UserConfig::$PLANS);


		// Local theme overrides
		if (!is_null(UserConfig::$theme_override) && file_exists(UserConfig::$theme_override . '/templates/')) {
			$template_folders[] = UserConfig::$theme_override . '/templates/';
		}

		// requested theme
		$template_folders[] = dirname(__DIR__) . '/themes/' . UserConfig::$theme . '/templates/';

		// determining the parent theme
		$theme_definition_file = dirname(__DIR__) . '/themes/' . UserConfig::$theme . '/theme.json';
		if (file_exists($theme_definition_file)) {
			$theme = json_decode(file_get_contents($theme_definition_file), TRUE);
			if (is_array($theme) && array_key_exists('parent', $theme)) {
				$template_folders[] = dirname(__DIR__) . '/themes/' . $theme['parent'] . '/templates/';
			}
		}

		// Configuring the templating
		$loader = new \Twig_Loader_Filesystem(__DIR__);
		foreach ($template_folders as $folder) {
			$loader->addPath($folder, 'startupapi');
		}
		$loader->addPath(dirname(__DIR__) . '/admin/templates', 'startupapi-admin');

		self::$template = new \Twig_Environment($loader, UserConfig::$twig_options);

		// StartupAPI apis
		if (UserConfig::$enable_startupapi_apis) {
			\StartupAPI\API\Endpoint::registerCoreEndpoints();
		}

		// do this on each page view where StartupAPI code is executed
		CampaignTracker::preserveReferer();
		CampaignTracker::recordCampaignVariables();
	}

	/**
	 * @returns array Returns global Twig template variables for the page
	 */
	public static function getTemplateInfo() {
		require(dirname(__DIR__) . '/admin/settings.inc');

		// UserConfig
		$config_info = array();
		foreach ($config_variables as $section) {
			foreach ($section['groups'] as $group) {
				foreach ($group['settings'] as $setting) {
					// don't make secret values available to templates
					if ($setting->getType() == Setting::TYPE_SECRET) {
						continue;
					}

					$var_type = $setting::phpType();
					if (substr($var_type, -2) == '[]') {
						$var_type = substr($var_type, 0, -2);
					}
					if ($var_type == 'int' || $var_type == 'string' || $var_type == 'boolean') {
						if (substr($setting_type, -2) == '[]' && !is_array(UserConfig::${$setting->getName()})) {
							continue;
						}
						$config_info[$setting->getName()] = UserConfig::${$setting->getName()};
					}
				}
			}
		}
		$config_info['authentication_modules'] = array_map(function(AuthenticationModule $module) {
			return array(
				'id' => $module->getID(),
				'title' => $module->getTitle(),
				'is_compact' => $module->isCompact()
			);
		}, UserConfig::$authentication_modules);
		$config_info['maillist_exists'] = UserConfig::$maillist && file_exists(UserConfig::$maillist);

		// AUTH
		$auth_info = array(
			'CSRF_NONCE' => UserTools::$CSRF_NONCE
		);

		$current_user = User::get();
		$current_account = null;
		$accounts = array();
		if (!is_null($current_user)) {
			$auth_info['current_user']['id'] = $current_user->getID();
			$auth_info['current_user']['name'] = $current_user->getName();
			$auth_info['current_user']['username'] = $current_user->getUsername();
			$auth_info['current_user']['email'] = $current_user->getEmail();
			$auth_info['current_user']['is_email_verified'] = $current_user->isEmailVerified();
			$auth_info['current_user']['is_impersonated'] = $current_user->isImpersonated();
			if ($current_user->isImpersonated()) {
				$impersonator = $current_user->getImpersonator();
				$auth_info['impersonator']['id'] = $impersonator->getID();
				$auth_info['impersonator']['name'] = $impersonator->getName();
			}
			$auth_info['current_user']['is_admin'] = $current_user->isAdmin();
			$auth_info['current_user']['is_logged_in'] = TRUE;

			$current_account = $current_user->getCurrentAccount();
			$auth_info['current_account']['id'] = $current_account->getID();
			$auth_info['current_account']['name'] = $current_account->getName();

			$current_plan = $current_account->getPlan(); // can be FALSE
			if ($current_plan) {
				$auth_info['current_plan']['name'] = $current_plan->getName();
				$auth_info['current_plan']['description'] = $current_plan->getDescription();
			}

			$accounts = Account::getUserAccounts($current_user);
			foreach ($accounts as $account) {
				$account_info = array(
					'name' => $account->getName(),
					'id' => $account->getID()
				);

				$plan = $account->getPlan(); // can be FALSE
				if ($plan) {
					$account_info['plan']['name'] = $plan->getName();
					$account_info['plan']['description'] = $plan->getDescription();
				}

				if ($account->isTheSameAs($current_account)) {
					$account_info['current'] = true;
				}

				$auth_info['accounts'][] = $account_info;
			}
		} else {
			$auth_info['current_user']['is_logged_in'] = FALSE;
		}

		// PAGE
		$page_info = array(
			'YEAR' => date('Y')
		);

		if (UserConfig::$currentTOSVersion && is_callable(UserConfig::$onRenderTOSLinks)) {
			ob_start();
			call_user_func(UserConfig::$onRenderTOSLinks);
			$page_info['TOSlinks'] = ob_get_contents();
			ob_end_clean();
		}

		if (!is_null(UserConfig::$onLoginStripLinks)) {
			$links = call_user_func_array(UserConfig::$onLoginStripLinks, array($current_user, $current_account));
			if (is_array($links)) {
				foreach ($links as $link) {
					$page_info['extralinks'][] = $link;
				}
			}
		}

		// Power Strip
		$powerstrip_info = array(
			'nav_pills' => UserConfig::$powerStripNavPills,
			'show_navbar' => UserConfig::$powerStripShowNavbar,
			'inverted_navbar' => UserConfig::$powerStripInvertedNavbar,
			'pull_right' => UserConfig::$powerStripPullRight
		);

		return array(
			'UserConfig' => $config_info,
			'AUTH' => $auth_info,
			'PAGE' => $page_info,
			'POWERSTRIP' => $powerstrip_info,
			'APP' => UserConfig::$app_global_template_variables
		);
	}

}
