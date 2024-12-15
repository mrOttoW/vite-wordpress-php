<?php
/**
 * Manages the integration of ViteJS HMR & dev server with WordPress during development.
 *
 * @package ViteWordPress
 */

namespace ViteWordPress;

/**
 * Class DevServer
 */
class DevServer implements DevServerInterface {
	/**
	 * Vite server host.
	 *
	 * @var string
	 */
	protected string $vite_server_host;

	/**
	 * Vite server port.
	 *
	 * @var string
	 */
	protected string $vite_server_port = '5173';

	/**
	 * Vite plugin configuration array.
	 *
	 * @var array<string, string|bool>|null
	 */
	protected ?array $vite_config = null;

	/**
	 * Vite client hook priority.
	 *
	 * @var int
	 */
	protected int $vite_client_hook_priority = 5;

	/**
	 * Manifest resolver.
	 *
	 * @var ManifestResolverInterface|null
	 */
	protected ?ManifestResolverInterface $manifest = null;

	/**
	 * Resolved assets list.
	 *
	 * @var string[]
	 */
	protected array $resolved_assets = [];

	/**
	 * Constructor.
	 *
	 * @param ManifestResolverInterface|null $manifest Manifest resolver.
	 */
	public function __construct( ?ManifestResolverInterface $manifest = null ) {
		$this->vite_server_host = get_site_url();

		if ( null !== $manifest ) {
			$this->manifest = $manifest;
		}
	}

	/**
	 * Registers hooks and filters for the dev server.
	 *
	 * @return self
	 */
	public function register(): self {
		if ( $this->is_config_active() && $this->is_client_active() ) {
			add_action( 'wp_head', [ $this, 'inject_vite_client' ], $this->vite_client_hook_priority );
			add_action( 'init', [ $this, 'modify_wp_import_map_hook' ] );
			add_filter( 'body_class', [ $this, 'filter_body_class' ], 999 );
			add_filter( 'script_module_loader_src', [ $this, 'modify_asset_loader_src' ], 999, 2 );
			add_filter( 'script_loader_src', [ $this, 'modify_asset_loader_src' ], 999, 2 );
			add_filter( 'style_loader_src', [ $this, 'modify_asset_loader_src' ], 999, 2 );
			add_filter( 'script_loader_tag', [ $this, 'modify_asset_loader_tags' ], 999, 3 );
		}

		return $this;
	}

	/**
	 * Sets the Vite server host.
	 *
	 * @param string $server_host The server host.
	 *
	 * @return self
	 */
	public function set_server_host( string $server_host ): self {
		$this->vite_server_host = $server_host;

		return $this;
	}

	/**
	 * Sets the Vite server port.
	 *
	 * @param int $server_port The server port.
	 *
	 * @return self
	 */
	public function set_server_port( int $server_port ): self {
		$this->vite_server_port = (string) $server_port;

		return $this;
	}

	/**
	 * Sets the priority for the Vite client hook.
	 *
	 * @param int $level The priority level.
	 *
	 * @return self
	 */
	public function set_client_hook( int $level ): self {
		$this->vite_client_hook_priority = $level;

		return $this;
	}

	/**
	 * Makes a request to the Vite dev server.
	 *
	 * @param string $api_url API endpoint URL.
	 *
	 * @return array{
	 *     errors: string|null,
	 *     response: int,
	 *     data: array<string, mixed>
	 * }
	 */
	protected function vite_server_request( string $api_url ): array {
		// phpcs:disable WordPress.WP.AlternativeFunctions
		$curl    = curl_init();
		$options = [
			CURLOPT_URL            => $api_url,
			CURLOPT_RETURNTRANSFER => true,
		];

		curl_setopt_array( $curl, $options );

		$json_data = curl_exec( $curl );
		$errors    = curl_error( $curl ) ? curl_error( $curl ) : null;
		$response  = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

		curl_close( $curl );
		// phpcs:enable

		$data = [];

		if ( null === $errors && $response >= 200 && $response < 300 ) {
			$data = json_decode( $json_data, true );
		}

		return [
			'errors'   => $errors,
			'response' => $response,
			'data'     => $data,
		];
	}

	/**
	 * Checks if the Vite client is active.
	 *
	 * @return bool True if the client is active, false otherwise.
	 */
	public function is_client_active(): bool {
		$request = $this->vite_server_request( $this->get_client_url() );

		return ! ( ! empty( $request['errors'] ) || ( $request['response'] ?? 0 ) !== 200 );
	}

	/**
	 * Checks if the Vite plugin config is active.
	 *
	 * @return bool True if the plugin config is active, false otherwise.
	 */
	public function is_config_active(): bool {
		$request = $this->vite_server_request( $this->get_config_url() );

		if ( ! empty( $request['errors'] ) || ( $request['response'] ?? 0 ) !== 200 ) {
			return false;
		}

		$this->vite_config = $request['data'];

		return true;
	}

	/**
	 * Injects the Vite.js script into the WordPress head section.
	 *
	 * @return void
	 */
	public function inject_vite_client(): void {
		// phpcs:disable WordPress.WP.EnqueuedResources
		?>
        <script type="module" src="<?php echo esc_url( $this->get_client_url() ); ?>"></script>
        <script type="module">window.process = {env: {NODE_ENV: 'development'}};</script>
		<?php
		// phpcs:enable
	}

	/**
	 * Adds a class to the body element if the Vite dev server is active.
	 *
	 * @param string[] $classes Body classes.
	 *
	 * @return string[]
	 */
	public function filter_body_class( array $classes ): array {
		$classes[] = 'vite-dev-server-is-active';

		return $classes;
	}

	/**
	 * Modifies the WordPress import map hook.
	 *
	 * Ensures that the WP import map is loaded before the Vite client script (for modules).
	 *
	 * @return void
	 */
	public function modify_wp_import_map_hook(): void {
		global $wp_script_modules;

		if ( isset( $wp_script_modules ) ) {
			$position = wp_is_block_theme() ? 'wp_head' : 'wp_footer';
			remove_action( $position, [ $wp_script_modules, 'print_import_map' ] );
			add_action( $position, [ $wp_script_modules, 'print_import_map' ], $this->vite_client_hook_priority - 1 );
		}
	}

	/**
	 * Modifies the script loader src and replaces it with the un-compiled URL
	 * on the dev server.
	 *
	 * @param string $src The original script source URL.
	 * @param string $id The script ID/Handle.
	 *
	 * @return string The modified or original source URL.
	 */
	public function modify_asset_loader_src( string $src, string $id ): string {
		if ( ! $this->contains_base( $src ) ) {
			return $src;
		}

		// Remove query parameters and base path to get the file name.
		$file_out_dir_path = preg_replace( '/\?.*$/', '', $src );
		$file_out_dir_path = explode( "{$this->vite_config['base']}/{$this->vite_config['outDir']}/", $file_out_dir_path );

		if ( ! isset( $file_out_dir_path[1] ) ) {
			return $src;
		}

		$resolved = false;

		// Check if manifest exists and resolve from the manifest.
		if ( isset( $this->manifest ) ) {
			$manifest_entry = $this->manifest->get_by_file( $file_out_dir_path[1] );
			if ( $manifest_entry && isset( $manifest_entry['src'] ) ) {
				$resolved = "{$this->get_base_url()}/{$manifest_entry['src']}";
			}
		}

		// If the file wasn't resolved via the manifest, try to resolve from the file system.
		if ( ! $resolved ) {
			$file_name        = str_replace( '.css', ".{$this->vite_config['css']}", $file_out_dir_path[1] );
			$file_system_path = "{$this->get_server_path()}/{$this->vite_config['srcDir']}/{$file_name}";

			if ( file_exists( $file_system_path ) ) {
				$resolved = "{$this->get_base_url()}/{$this->vite_config['srcDir']}/{$file_name}";
			}
		}

		// If resolved, update src and track the asset; otherwise, return the original src.
		if ( $resolved && ! isset( $this->resolved_assets[ $id ] ) ) {
			$this->resolved_assets[ $id ] = $resolved;

			return $resolved;
		}

		return $src;
	}

	/**
	 * Ensures we use the import syntax when loading un-compiled scripts from the
	 * dev server.
	 *
	 * This function ensures that block esModules are not affected and only
	 * modifies the required scripts.
	 *
	 * @param string $tag The original script tag.
	 * @param string $id The script ID/Handle.
	 * @param string $src The script source URL.
	 *
	 * @return string The modified script tag or the original.
	 */
	public function modify_asset_loader_tags( string $tag, string $id, string $src ): string {
		/** At this point the src already got modified by {@see self::modify_asset_loader_src()} */
		if ( $this->contains_server_url( $src ) && isset( $this->resolved_assets[ $id ] ) ) {
			return '<script type="module" src="' . esc_url( $src ) . '"></script>'; // phpcs:disable WordPress.WP.EnqueuedResources
		}

		return $tag;
	}

	/**
	 * Check if the URL contains the base path.
	 *
	 * @param string $url Source URL.
	 *
	 * @return bool Whether the URL contains the base path.
	 */
	public function contains_base( string $url ): bool {
		if ( '' !== $this->vite_config['base'] && false !== strpos( $url, $this->vite_config['base'] ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Check if the URL contains the server base URL.
	 *
	 * @param string $url Source URL.
	 *
	 * @return bool Whether the URL contains the server base URL.
	 */
	public function contains_server_url( string $url ): bool {
		if ( '' !== $this->get_base_url() && false !== strpos( $url, $this->get_base_url() ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the URL to the Vite plugin configuration.
	 *
	 * @return string Full URL to the Vite plugin config (eg: my-host:5173/vite-wordpress.json).
	 */
	public function get_config_url(): string {
		return "{$this->get_server_url()}/vite-wordpress.json";
	}

	/**
	 * Get the URL to the Vite client.
	 *
	 * @return string Full URL to the Vite client (eg: my-host:5173/wp-content/plugins/my-plugin/@vite/client).
	 */
	public function get_client_url(): string {
		return "{$this->get_base_url()}/@vite/client";
	}

	/**
	 * Get the server base URL for Vite.
	 *
	 * @return string The base URL (eg: my-host:5173/wp-content/plugins/my-plugin).
	 */
	public function get_base_url(): string {
		return untrailingslashit( "{$this->get_server_url()}{$this->vite_config['base']}" );
	}

	/**
	 * Get the server URL for Vite.
	 *
	 * @return string The base URL (eg: my-host:5173).
	 */
	public function get_server_url(): string {
		return "{$this->vite_server_host}:{$this->vite_server_port}";
	}

	/**
	 * Get the server path for Vite.
	 *
	 * @return string Server path (eg: server/path/wp-content/plugins/my-plugin).
	 */
	public function get_server_path(): string {
		return untrailingslashit( ABSPATH ) . $this->vite_config['base'];
	}
}
