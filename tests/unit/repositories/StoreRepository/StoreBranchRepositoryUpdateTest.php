<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use IngredientRepository;
use ProductRepository;
use StoreBranch;
use StoreBranchRepository;
use WP_UnitTestCase;

/**
 * Exercises the mutator helpers recently added to StoreBranchRepository.
 *
 * @coversNothing
 */
class StoreBranchRepositoryUpdateTest extends WP_UnitTestCase
{
    private StoreBranchRepository $branchRepo;
    private ProductRepository     $productRepo;
    private IngredientRepository  $ingRepo;

    private int $branchId;

    public function set_up(): void
    {
        parent::set_up();

        // Taxonomies needed by ProductRepository
        register_taxonomy('product_cat', 'product');
        register_taxonomy('product_tag', 'product');

        $this->branchRepo  = new StoreBranchRepository();
        $this->productRepo = new ProductRepository();
        $this->ingRepo     = new IngredientRepository();

        /* minimal branch */
        $this->branchId = $this->branchRepo->create([
            'name'      => 'Original',
            'phone'     => '000',
            'city'      => 'Nowhere',
            'address'   => 'Road 0',
            'is_open'   => false,
            'activity_times'=> [],
            'kosher_type'=> '',
            'accessibility_list'=> [],
        ]);
    }

    /* ------------------------------------------------------------------ *
     * Scalar setters
     * ------------------------------------------------------------------ */
    public function test_scalar_setters_persist_and_round_trip(): void
    {
        $this->branchRepo->setName($this->branchId, 'Downtown');
        $this->branchRepo->setPhone($this->branchId, '03-777-0000');
        $this->branchRepo->setCity($this->branchId, 'Tel Aviv');
        $this->branchRepo->setAddress($this->branchId, '42 Main St');
        $this->branchRepo->setIsOpen($this->branchId, true);

        $b = $this->branchRepo->get($this->branchId);
        $this->assertSame('Downtown',  $b->name);
        $this->assertSame('03-777-0000', $b->phone);
        $this->assertSame('Tel Aviv',  $b->city);
        $this->assertSame('42 Main St',$b->address);
        $this->assertTrue($b->is_open);
    }

    /* ------------------------------------------------------------------ *
     * Activity times, kosher & accessibility
     * ------------------------------------------------------------------ */
    public function test_activity_time_kosher_and_accessibility_mutations(): void
    {
        /* add slots */
        $this->branchRepo->addActivityTime($this->branchId,'SUNDAY', '08:00-12:00');
        $this->branchRepo->addActivityTime($this->branchId,'SUNDAY', '14:00-18:00'); // second slot

        /* kosher + accessibility */
        $this->branchRepo->setKosherType($this->branchId, 'Kosher Mehadrin');
        $this->branchRepo->addAccessibility($this->branchId, 'Wheelchair');

        $b = $this->branchRepo->get($this->branchId);
        $this->assertEqualsCanonicalizing(
            ['08:00-12:00','14:00-18:00'],
            $b->activity_times['SUNDAY']
        );
        $this->assertSame('Kosher Mehadrin', $b->kosher_type);
        $this->assertContains('Wheelchair',  $b->accessibility_list);

        /* remove slot, clear kosher, remove accessibility */
        $this->branchRepo->removeActivityTime($this->branchId,'SUNDAY', '08:00-12:00');
        $this->branchRepo->clearKosherType($this->branchId);
        $this->branchRepo->removeAccessibility($this->branchId,'Wheelchair');

        $b = $this->branchRepo->get($this->branchId);
        $this->assertSame(['14:00-18:00'], $b->activity_times['SUNDAY']);
        $this->assertSame('',              $b->kosher_type);
        $this->assertNotContains('Wheelchair', $b->accessibility_list);
    }

    /* ------------------------------------------------------------------ *
     * Product / ingredient add-remove & availability
     * ------------------------------------------------------------------ */
    public function test_product_and_ingredient_management(): void
    {
        /* create a product and ingredient */
        $pid = $this->productRepo->create(['name'=>'Burger','price'=>30]);
        $iid = $this->ingRepo->create(['name'=>'Salt','price'=>0]);

        /* add with default active=true */
        $this->branchRepo->addProduct($this->branchId, $pid);
        $this->branchRepo->addIngredient($this->branchId, $iid, false); // inactive

        /* availability flip */
        $this->branchRepo->setIngredientAvailability($this->branchId, $iid, true);

        $b = $this->branchRepo->get($this->branchId);
        $this->assertArrayHasKey($pid, $b->product_availability);
        $this->assertTrue($b->isProductAvailable($pid));
        $this->assertTrue($b->isIngredientAvailable($iid));

        /* remove product & ingredient */
        $this->branchRepo->removeProduct($this->branchId, $pid);
        $this->branchRepo->removeIngredient($this->branchId, $iid);

        $b = $this->branchRepo->get($this->branchId);
        $this->assertFalse($b->isProductAvailable($pid));
        $this->assertFalse($b->isIngredientAvailable($iid));
    } 

    public function test_addActivityTime_invalid_day_throws(): void
    {
        $branchRepo = new StoreBranchRepository();

        /** minimal payload that satisfies required fields */
        $branchId = $branchRepo->create([
            'name'               => 'Temp',
            'phone'              => '000',
            'city'               => 'Nowhere',
            'address'            => '1 Null St',
            'is_open'            => false,
            'activity_times'     => [],
            'kosher_type'        => '',
            'accessibility_list' => [],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $branchRepo->addActivityTime($branchId, 'Funday', '10:00-12:00');
    }

    /** happy-path check */
    public function test_addActivityTime_valid_day_round_trip(): void
    {
        $branchRepo = new StoreBranchRepository();

        $branchId = $branchRepo->create([
            'name'=>'Temp','phone'=>'0','city'=>'X','address'=>'Y',
            'is_open'=>true,'activity_times'=>[],
            'kosher_type'=>'','accessibility_list'=>[],
        ]);

        $branchRepo->addActivityTime($branchId, 'saturday', '18:00-22:00');

        $branch = $branchRepo->get($branchId);
        $this->assertSame(['18:00-22:00'], $branch->activity_times['SATURDAY']);
    }
    
}
