<?php
/**
 * Class for gadgets based on properties obtained from a JSON blob.
 * 
 * The format of the JSON blob is as follows:
 * {
 *   "settings": {
 *     // The rights required to be able to enable this gadget
 *     "rights": ["delete", "block"],
 *     // Whether this gadget is enabled by default
 *     "default": true
 *     // Whether this gadget is hidden
 *     "hidden": false,
 *     // Whether this gadget is shared
 *     "shared": true,
 *     // The key of the section this gadget belongs to. The section title message key is gadgetsection-$section-title
 *     "section": "key of gadget section",
 *   },
 *   "module": {
 *     // scripts and styles are pages in the Gadget: namespace
 *     "scripts": ["foobar.js"],
 *     "styles": ["foobar.css"],
 *     "messages": ["foobar-welcome", "recentchanges", "namespaces"],
 *     "dependencies": ["jquery.ui.tabs", "gadget.awesome"]
 *   }
 * }
 */
class Gadget {
	/** Gadget name (string) */
	protected $name;
	
	/** Gadget repository this gadget came from (GadgetRepo object) */
	protected $repo;
	
	/** Last modification timestamp of the gadget metadata (TS_MW timestamp) **/
	protected $timestamp;
	
	/** array of gadget settings, see "settings" key in the JSON blob */
	protected $settings;
	
	/** array of module settings, see "module" key in the JSON blob */
	protected $moduleData;
	
	/*** Public static methods ***/
	
	public static function isValidPropertiesArray( $properties ) {
		// TODO: Also validate existence of individual properties
		return is_array( $properties ) && isset( $properties['settings'] ) && isset( $properties['module'] );
	}
	
	/*** Public methods ***/
	
	/**
	 * Constructor
	 * @param $name string Name
	 * @param $repo GadgetRepo that this gadget came from
	 * @param $properties mixed Array or JSON blob (string) with settings and module info
	 * @param $timestamp string Timestamp (TS_MW) this gadget's metadata was last touched
	 * @throws MWException if $properties is invalid
	 */
	public function __construct( $name, $repo, $properties, $timestamp ) {
		if ( is_string( $properties ) ) {
			$properties = FormatJson::decode( $properties, true );
		}
		
		
		if ( !self::isValidPropertiesArray( $properties ) ) {
			throw new MWException( 'Invalid property array passed to ' . __METHOD__ );
		}
		
		$this->name = $name;
		$this->repo = $repo;
		$this->timestamp = $timestamp;
		$this->settings = $properties['settings'];
		$this->moduleData = $properties['module'];
	}
	
	/**
	 * Retrieve the JSON representation of this gadget, in the same format as $properties in __construct().
	 * @return string JSON
	 */
	public function getJSON() {
		return FormatJson::encode( array( 'settings' => $this->settings, 'module' => $this->moduleData ) );
	}
	
	/**
	 * Get the name of the gadget. This name must be unique within its repository and must never change.
	 * It is only used internally; the name displayed to the user is controlled by getNameMsg().
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}
	
	/**
	 * Get the name of the ResourceLoader source for this gadget's module
	 * @return string
	 */
	public function getRepo() { 
		return $this->repo;
	}
	 
	/**
	 * Get the last modification timestamp of the gadget metadata
	 * @return string TS_MW timestamp
	 */
	public function getTimestamp() {
		return $this->timestamp;
	}
	
	/**
	 * Get the title message for this gadget. This is the interface message that controls the name of the
	 * gadget as shown to the user.
	 * @return string Message key
	 */
	public function getTitleMsg() {
		return "gadget-{$this->name}-title";
	}
	
	/**
	 * Get the description message for this gadget.
	 * @return string Message key
	 */
	public function getDescriptionMsg() {
		return "gadget-{$this->name}-desc";
	}
	
	/**
	 * Get the section (also called category) this gadget is in.
	 * @return string Section key or empty string if not in any section
	 */
	public function getSection() {
		return $this->settings['section'];
	}
	
	/**
	 * Check whether this gadget is enabled by default. Even these gadgets can be disabled in the user's
	 * preferences, the preference just defaults to being on.
	 * @return bool
	 */
	public function isEnabledByDefault() {
		return (bool)$this->settings['default'];
	}
	
	/**
	 * Get the rights a user needs to be allowed to enable this gadget.
	 * @return array of rights (strings), empty if no restrictions
	 */
	public function getRequiredRights() {
		return (array)$this->settings['rights'];
	}
	
	/**
	 * Check whether this module is public. Modules that are not public cannot be enabled by users in
	 * their preferences and are only visible in the gadget manager.
	 * @return bool
	 */
	public function isHidden() {
		return (bool)$this->settings['hidden'];
	}
	
	/**
	 * Check whether this module is shared. Modules that are not shared cannot be loaded through
	 * a foreign repository.
	 * @return bool
	 */
	public function isShared() {
		return (bool)$this->settings['shared'];
	}
	
	/**
	 * Get the ResourceLoader module for this gadget, if available.
	 * @return ResourceLoaderModule object
	 */
	public function getModule() {
		// Build $pages
		$pages = array();
		foreach ( $this->moduleData['scripts'] as $script ) {
			$pages["Gadget:$script"] = array( 'type' => 'script' );
		}
		foreach ( $this->moduleData['styles'] as $style ) {
			$pages["Gadget:$style"] = array( 'type' => 'style' );
		}
		
		return new GadgetResourceLoaderModule(
			$pages,
			(array)$this->moduleData['dependencies'],
			(array)$this->moduleData['messages'],
			$this->repo->getSource(),
			$this->timestamp,
			$this->repo->getDB()
		);
	}
	
	/**
	 * Get the name of the ResourceLoader module for this gadget.
	 * @return string Module name
	 */
	public function getModuleName() {
		return "gadget.{$this->name}";
	}
	
	public function getScripts() {
		return $this->moduleData['scripts'];
	}
	
	public function getStyles() {
		return $this->moduleData['styles'];
	}
	
	/*** Public methods ***/
	
	/**
	 * Check whether this gadget is enabled for a given user.
	 * @param $user User object
	 * @return bool
	 */
	public function isEnabledForUser( $user ) {
		$name = $this->getName();
		return (bool)$user->getOption( "gadget-$name", $this->isEnabledByDefault() );
	}
	
	/**
	 * Checks whether a given user has the required rights to use this gadget
	 *
	 * @param $user User: user to check against
	 * @return Boolean
	 */
	public function isAllowed( $user ) {
		$required = $this->getRequiredRights();
		$numRequired = count( $required );
		if ( $numRequired === 0 ) {
			// Short circuit to prevent calling $user->getRights()
			return true;
		}
		return count( array_intersect( $required, $user->getRights() ) ) == $numRequired;
	}
}
