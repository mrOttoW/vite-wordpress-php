<?php
/**
 * Interface for the Dev Server.
 *
 * @package ViteWordPress
 */

namespace ViteWordPress;

/**
 * Interface DevServerInterface
 */
interface DevServerInterface {
	/**
	 * Registers hooks and filters for the dev server.
	 *
	 * @return self
	 */
	public function register(): self;

	/**
	 * Sets the Vite server host.
	 *
	 * @param string $server_host The server host.
	 *
	 * @return self
	 */
	public function set_server_host( string $server_host ): self;

	/**
	 * Sets the Vite server port.
	 *
	 * @param int $server_port The server port.
	 *
	 * @return self
	 */
	public function set_server_port( int $server_port ): self;

	/**
	 * Sets the priority for the Vite client hook.
	 *
	 * @param int $level The priority level.
	 *
	 * @return self
	 */
	public function set_client_hook( int $level ): self;

	/**
	 * Checks if the Vite client is active.
	 *
	 * @return bool True if the client is active, false otherwise.
	 */
	public function is_client_active(): bool;

	/**
	 * Checks if the Vite plugin config is active.
	 *
	 * @return bool True if the plugin config is active, false otherwise.
	 */
	public function is_config_active(): bool;

	/**
	 * Check if the URL contains the base path.
	 *
	 * @param string $url Source URL.
	 *
	 * @return bool Whether the URL contains the base path.
	 */
	public function contains_base( string $url ): bool;

	/**
	 * Check if the URL contains the server base URL.
	 *
	 * @param string $url Source URL.
	 *
	 * @return bool Whether the URL contains the server base URL.
	 */
	public function contains_server_url( string $url ): bool;

	/**
	 * Get the URL to the Vite plugin configuration.
	 *
	 * @return string Full URL to the Vite plugin config (eg: my-host:5173/vite-wordpress.json).
	 */
	public function get_config_url(): string;

	/**
	 * Get the URL to the Vite client.
	 *
	 * @return string Full URL to the Vite client (eg: my-host:5173/wp-content/plugins/my-plugin/@vite/client).
	 */
	public function get_client_url(): string;

	/**
	 * Get the server base URL for Vite.
	 *
	 * @return string The base URL (eg: my-host:5173/wp-content/plugins/my-plugin).
	 */
	public function get_base_url(): string;

	/**
	 * Get the server URL for Vite.
	 *
	 * @return string The base URL (eg: my-host:5173).
	 */
	public function get_server_url(): string;

	/**
	 * Get the server path for Vite.
	 *
	 * @return string Server path (eg: server/path/wp-content/plugins/my-plugin).
	 */
	public function get_server_path(): string;
}
