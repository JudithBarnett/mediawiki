<?php

namespace MediaWiki\Tests\Revision;

use MediaWikiIntegrationTestCase;

/**
 * Tests RevisionStore against the post-migration MCR DB schema.
 *
 * @group RevisionStore
 * @group Storage
 * @group Database
 */
class RevisionQueryInfoTest extends MediaWikiIntegrationTestCase {

	protected static function getRevisionQueryFields( $returnTextIdField = true ) {
		$fields = [
			'rev_id',
			'rev_page',
			'rev_timestamp',
			'rev_minor_edit',
			'rev_deleted',
			'rev_len',
			'rev_parent_id',
			'rev_sha1',
		];
		if ( $returnTextIdField ) {
			$fields[] = 'rev_text_id';
		}
		return $fields;
	}

	protected static function getArchiveQueryFields( $returnTextFields = true ) {
		$fields = [
			'ar_id',
			'ar_page_id',
			'ar_namespace',
			'ar_title',
			'ar_rev_id',
			'ar_timestamp',
			'ar_minor_edit',
			'ar_deleted',
			'ar_len',
			'ar_parent_id',
			'ar_sha1',
		];
		if ( $returnTextFields ) {
			$fields[] = 'ar_text_id';
		}
		return $fields;
	}

	protected static function getCommentQueryFields( $prefix ) {
		return [
			"{$prefix}_comment_text" => "comment_{$prefix}_comment.comment_text",
			"{$prefix}_comment_data" => "comment_{$prefix}_comment.comment_data",
			"{$prefix}_comment_cid" => "comment_{$prefix}_comment.comment_id",
		];
	}

	protected static function getActorQueryFields( $prefix, $tmp = false ) {
		if ( $tmp ) {
			return [
				"{$prefix}_user" => "actor_{$prefix}_user.actor_user",
				"{$prefix}_user_text" => "actor_{$prefix}_user.actor_name",
				"{$prefix}_actor" => "temp_{$prefix}_user.{$prefix}actor_actor",
			];
		} elseif ( $prefix === 'ar' ) {
			return [
				"{$prefix}_actor",
				"{$prefix}_user" => 'archive_actor.actor_user',
				"{$prefix}_user_text" => 'archive_actor.actor_name',
			];
		} else {
			return [
				"{$prefix}_actor" => "{$prefix}_actor",
				"{$prefix}_user" => "actor_{$prefix}_user.actor_user",
				"{$prefix}_user_text" => "actor_{$prefix}_user.actor_name",
			];
		}
	}

	protected function getTextQueryFields() {
		return [
			'old_text',
			'old_flags',
		];
	}

	protected static function getPageQueryFields() {
		return [
			'page_namespace',
			'page_title',
			'page_id',
			'page_latest',
			'page_is_redirect',
			'page_len',
		];
	}

	protected static function getUserQueryFields() {
		return [
			'user_name',
		];
	}

	protected function getContentHandlerQueryFields( $prefix ) {
		return [
			"{$prefix}_content_format",
			"{$prefix}_content_model",
		];
	}

	public static function provideArchiveQueryInfo() {
		yield 'no options' => [
			[],
			[
				'tables' => [
					'archive',
					'archive_actor' => 'actor',
					'comment_ar_comment' => 'comment',
				],
				'fields' => array_merge(
					self::getArchiveQueryFields( false ),
					self::getActorQueryFields( 'ar' ),
					self::getCommentQueryFields( 'ar' )
				),
				'joins' => [
					'comment_ar_comment'
						=> [ 'JOIN', 'comment_ar_comment.comment_id = ar_comment_id' ],
					'archive_actor' => [ 'JOIN', 'actor_id=ar_actor' ],
				],
			]
		];
	}

	public static function provideQueryInfo() {
		// TODO: more option variations
		yield 'page and user option, actor-new' => [
			[],
			[ 'page', 'user' ],
			[
				'tables' => [
					'revision',
					'page',
					'user',
					'actor_rev_user' => 'actor',
					'comment_rev_comment' => 'comment',
				],
				'fields' => array_merge(
					self::getRevisionQueryFields( false ),
					self::getPageQueryFields(),
					self::getUserQueryFields(),
					self::getActorQueryFields( 'rev' ),
					self::getCommentQueryFields( 'rev' )
				),
				'joins' => [
					'page' => [ 'JOIN', [ 'page_id = rev_page' ] ],
					'user' => [
						'LEFT JOIN',
						[ 'actor_rev_user.actor_user != 0', 'user_id = actor_rev_user.actor_user' ],
					],
					'comment_rev_comment' => [ 'JOIN', 'comment_rev_comment.comment_id = rev_comment_id' ],
					'actor_rev_user' => [ 'JOIN', 'actor_rev_user.actor_id = rev_actor' ]
				],
			]
		];
		yield 'no options, actor-new' => [
			[],
			[],
			[
				'tables' => [
					'revision',
					'actor_rev_user' => 'actor',
					'comment_rev_comment' => 'comment',
				],
				'fields' => array_merge(
					self::getRevisionQueryFields( false ),
					self::getActorQueryFields( 'rev' ),
					self::getCommentQueryFields( 'rev' )
				),
				'joins' => [
					'comment_rev_comment' => [ 'JOIN', 'comment_rev_comment.comment_id = rev_comment_id' ],
					'actor_rev_user' => [ 'JOIN', 'actor_rev_user.actor_id = rev_actor' ],
				]
			]
		];
	}

	public static function provideSlotsQueryInfo() {
		yield 'no options' => [
			[],
			[],
			[
				'tables' => [
					'slots'
				],
				'fields' => [
					'slot_revision_id',
					'slot_content_id',
					'slot_origin',
					'slot_role_id',
				],
				'joins' => [],
				'keys' => [
					'rev_id' => 'slot_revision_id',
					'role_id' => 'slot_role_id'
				],
			]
		];
		yield 'role option' => [
			[],
			[ 'role' ],
			[
				'tables' => [
					'slots',
					'slot_roles',
				],
				'fields' => [
					'slot_revision_id',
					'slot_content_id',
					'slot_origin',
					'slot_role_id',
					'role_name',
				],
				'joins' => [
					'slot_roles' => [ 'LEFT JOIN', [ 'slot_role_id = role_id' ] ],
				],
				'keys' => [
					'rev_id' => 'slot_revision_id',
					'role_id' => 'slot_role_id'
				],
			]
		];
		yield 'content option' => [
			[],
			[ 'content' ],
			[
				'tables' => [
					'slots',
					'content',
				],
				'fields' => [
					'slot_revision_id',
					'slot_content_id',
					'slot_origin',
					'slot_role_id',
					'content_size',
					'content_sha1',
					'content_address',
					'content_model',
				],
				'joins' => [
					'content' => [ 'JOIN', [ 'slot_content_id = content_id' ] ],
				],
				'keys' => [
					'rev_id' => 'slot_revision_id',
					'role_id' => 'slot_role_id',
					'model_id' => 'content_model',
				],
			]
		];
		yield 'content and model options' => [
			[],
			[ 'content', 'model' ],
			[
				'tables' => [
					'slots',
					'content',
					'content_models',
				],
				'fields' => [
					'slot_revision_id',
					'slot_content_id',
					'slot_origin',
					'slot_role_id',
					'content_size',
					'content_sha1',
					'content_address',
					'content_model',
					'model_name',
				],
				'joins' => [
					'content' => [ 'JOIN', [ 'slot_content_id = content_id' ] ],
					'content_models' => [ 'LEFT JOIN', [ 'content_model = model_id' ] ],
				],
				'keys' => [
					'rev_id' => 'slot_revision_id',
					'role_id' => 'slot_role_id',
					'model_id' => 'content_model',
				],
			]
		];
	}

	/**
	 * @dataProvider provideQueryInfo
	 * @covers \MediaWiki\Revision\RevisionStore::getQueryInfo
	 */
	public function testRevisionStoreGetQueryInfo( $migrationStageSettings, $options, $expected ) {
		$this->overrideConfigValues( $migrationStageSettings );

		$store = $this->getServiceContainer()->getRevisionStore();

		$queryInfo = $store->getQueryInfo( $options );
		$this->assertQueryInfoEquals( $expected, $queryInfo );
	}

	/**
	 * @dataProvider provideSlotsQueryInfo
	 * @covers \MediaWiki\Revision\RevisionStore::getSlotsQueryInfo
	 */
	public function testRevisionStoreGetSlotsQueryInfo(
		$migrationStageSettings,
		$options,
		$expected
	) {
		$this->overrideConfigValues( $migrationStageSettings );

		$store = $this->getServiceContainer()->getRevisionStore();

		$queryInfo = $store->getSlotsQueryInfo( $options );
		$this->assertQueryInfoEquals( $expected, $queryInfo );
	}

	/**
	 * @dataProvider provideArchiveQueryInfo
	 * @covers \MediaWiki\Revision\RevisionStore::getArchiveQueryInfo
	 */
	public function testRevisionStoreGetArchiveQueryInfo( $migrationStageSettings, $expected ) {
		$this->overrideConfigValues( $migrationStageSettings );

		$store = $this->getServiceContainer()->getRevisionStore();

		$queryInfo = $store->getArchiveQueryInfo();
		$this->assertQueryInfoEquals( $expected, $queryInfo );
	}

	private function assertQueryInfoEquals( $expected, $queryInfo ) {
		$this->assertArrayEqualsIgnoringIntKeyOrder(
			$expected['tables'],
			$queryInfo['tables'],
			'tables'
		);
		$this->assertArrayEqualsIgnoringIntKeyOrder(
			$expected['fields'],
			$queryInfo['fields'],
			'fields'
		);
		$this->assertArrayEqualsIgnoringIntKeyOrder(
			$expected['joins'],
			$queryInfo['joins'],
			'joins'
		);
		if ( isset( $expected['keys'] ) ) {
			$this->assertArrayEqualsIgnoringIntKeyOrder(
				$expected['keys'],
				$queryInfo['keys'],
				'keys'
			);
		}
	}

	/**
	 * Assert that the two arrays passed are equal, ignoring the order of the values that integer
	 * keys.
	 *
	 * Note: Failures of this assertion can be slightly confusing as the arrays are actually
	 * split into a string key array and an int key array before assertions occur.
	 *
	 * @param array $expected
	 * @param array $actual
	 * @param string|null $message
	 */
	private function assertArrayEqualsIgnoringIntKeyOrder(
		array $expected,
		array $actual,
		$message = null
	) {
		$this->objectAssociativeSort( $expected );
		$this->objectAssociativeSort( $actual );

		// Separate the int key values from the string key values so that assertion failures are
		// easier to understand.
		$expectedIntKeyValues = [];
		$actualIntKeyValues = [];

		// Remove all int keys and re add them at the end after sorting by value
		// This will result in all int keys being in the same order with same ints at the end of
		// the array
		foreach ( $expected as $key => $value ) {
			if ( is_int( $key ) ) {
				unset( $expected[$key] );
				$expectedIntKeyValues[] = $value;
			}
		}
		foreach ( $actual as $key => $value ) {
			if ( is_int( $key ) ) {
				unset( $actual[$key] );
				$actualIntKeyValues[] = $value;
			}
		}

		$this->objectAssociativeSort( $expected );
		$this->objectAssociativeSort( $actual );

		$this->objectAssociativeSort( $expectedIntKeyValues );
		$this->objectAssociativeSort( $actualIntKeyValues );

		$this->assertEquals( $expected, $actual, $message );
		$this->assertEquals( $expectedIntKeyValues, $actualIntKeyValues, $message );
	}

}
