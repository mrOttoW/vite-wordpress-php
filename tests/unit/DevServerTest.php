<?php declare( strict_types=1 );

use Brain\Monkey;
use PHPUnit\Framework\TestCase;
use ViteWordPress\DevServer;

class DevServerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Monkey\Functions\expect( 'get_site_url' )
			->once()
			->andReturn( 'https://example.com' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	public function testConstructorSetsDefaults(): void {
		$dev_server = new DevServer();

		$this->assertEquals( 'https://example.com', $dev_server->get_server_host() );
		$this->assertEquals( '5173', $dev_server->get_server_port() );
	}

	public function testSetServerHost(): void {
		$dev_server = new DevServer();
		$dev_server->set_server_host( 'https://new-server.com' );

		$this->assertEquals( 'https://new-server.com', $dev_server->get_server_host() );
	}

	public function testSetServerPort(): void {
		$dev_server = new DevServer();
		$dev_server->set_server_port( 1234 );

		$this->assertEquals( '1234', $dev_server->get_server_port() );
	}


	public function testIsClientActive(): void {
		$dev_server = $this->getMockBuilder( DevServer::class )
		                  ->onlyMethods( [ 'vite_server_request', 'get_client_url' ] )
		                  ->getMock();

		$dev_server->method( 'vite_server_request' )->willReturn( [
			'errors'   => null,
			'response' => 200,
		] );

		$this->assertTrue( $dev_server->is_client_active() );
	}

	public function testIsConfigActive(): void {
		$dev_server = $this->getMockBuilder( DevServer::class )
		                  ->onlyMethods( [ 'vite_server_request', 'get_config_url' ] )
		                  ->getMock();

		$dev_server->method( 'vite_server_request' )->willReturn( [
			'errors'   => null,
			'response' => 200,
			'data'     => [ 'base' => '/vite/' ],
		] );

		$this->assertTrue( $dev_server->is_config_active() );
		$this->assertEquals( [ 'base' => '/vite/' ], $dev_server->get_config() );
	}
}
