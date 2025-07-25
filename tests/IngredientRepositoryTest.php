<?php

/**
 * @covers IngredientRepository
 */
class IngredientRepositoryTest extends WP_UnitTestCase {

	private IngredientRepository $repo;

	protected function setUp(): void {
		parent::setUp();
		$this->repo = new IngredientRepository();
	}

	public function test_create_and_get(): void {
		$id = $this->repo->create([
			'name'  => 'Unit Lettuce',
			'price' => 1.25,
		]);

		$ingredient = $this->repo->get( $id );

		$this->assertIsInt( $id );
		$this->assertNotNull( $ingredient );
		$this->assertSame( 'Unit Lettuce', $ingredient->name );
		$this->assertSame( 1.25,           $ingredient->price );
	}
}
