<?php
declare(strict_types=1);

namespace SquidlyCore\Tests\Integration;

use GroupItemRepository;
use IngredientRepository;
use ItemType;
use ProductGroup;
use ProductGroupRepository;
use ProductRepository;
use WP_UnitTestCase;
use PHPUnit\Framework\Constraint\IsType;

class HamburgerMenuSetupTest extends WP_UnitTestCase
{
    private ProductRepository     $prodRepo;
    private IngredientRepository  $ingRepo;
    private GroupItemRepository   $giRepo;
    private ProductGroupRepository $pgRepo;

    public function set_up(): void
    {
        parent::set_up();

        register_taxonomy('product_cat', 'product');
        register_taxonomy('product_tag', 'product');

        $this->prodRepo = new ProductRepository();
        $this->ingRepo  = new IngredientRepository();
        $this->giRepo   = new GroupItemRepository();
        $this->pgRepo   = new ProductGroupRepository();
    }

    /* -------------------------------------------------
     *  DEBUG helper — prints only with --debug flag
     * ------------------------------------------------*/
    private function dump(string $label, array $objects): void
    {
        if ( ! in_array('--debug', $_SERVER['argv'] ?? [], true) ) {
            return;            // keep CI logs clean
        }
        fwrite(STDERR, "\n=== $label ===\n");
        foreach ($objects as $o) {
            fwrite(STDERR, trim(print_r($o, true)) . PHP_EOL);
        }
    }

    public function test_complete_hamburger_setup(): void
    {
        /* 0) Main product “Hamburger” */
        $burgerId = $this->prodRepo->create([
            'name'=>'Hamburger','price'=>29.0,
            'description'=>'Our signature burger, crafted with a juicy, ...',
        ]);

        /* 1) Free ingredients — price 0 */
        $freeNames = ['Lettuce','Tomato','Pickles','Onion',
                      'Ketchup','Mayonnaise','Mustard','Cheese'];
        $freeGids = [];
        foreach ($freeNames as $n) {
            $iid = $this->ingRepo->create(['name'=>$n,'price'=>0.0]);
            $freeGids[] = $this->giRepo->create([
                'item_id'=>$iid,'item_type'=>ItemType::INGREDIENT,
            ]);
        }
        $freeGroupId = $this->pgRepo->create([
            'name'=>'Hamburger Free Ingredients',
            'type'=>ItemType::INGREDIENT,
            'group_item_ids'=>$freeGids,
        ]);

        /* 2) Paid add-ons with one override */
        $paidAddons = [
            'Bacon'=>5.0,'Fried Egg'=>4.0,'Avocado'=>6.0,'Jalapeños'=>3.0,'Extra Patty'=>12.0,
            'Onion Rings'=>4.5,'Blue Cheese'=>5.0,'Gouda'=>4.0,'Emmental'=>4.0,
            'Truffle Mayo'=>6.5,'Aioli'=>3.5,'BBQ Sauce'=>3.0,'Sriracha'=>3.0,
        ];
        $paidGids = [];
        foreach ($paidAddons as $n=>$price) {
            $pid = $this->prodRepo->create(['name'=>$n,'price'=>$price]);
            $paidGids[] = $this->giRepo->create([
                'item_id'=>$pid,'item_type'=>ItemType::PRODUCT,
                'override_price'=> $n === 'Bacon' ? 6.5 : null,
            ]);
        }
        $paidGroupId = $this->pgRepo->create([
            'name'=>'Hamburger Paid Add-ons','type'=>ItemType::PRODUCT,
            'group_item_ids'=>$paidGids,
        ]);

        /* 3) Sides */
        $sides = ['Fries'=>12,'Sweet Potato Fries'=>14,'Onion Rings'=>13,'Coleslaw'=>9,'Side Salad'=>10];
        $sideGids=[];
        foreach($sides as $n=>$p){
            $sideGids[]=$this->giRepo->create([
                'item_id'=>$this->prodRepo->create(['name'=>$n,'price'=>$p]),
                'item_type'=>ItemType::PRODUCT,
            ]);
        }
        $sideGroupId=$this->pgRepo->create([
            'name'=>'Sides','type'=>ItemType::PRODUCT,'group_item_ids'=>$sideGids,
        ]);

        /* 4) Drinks */
        $drinks=['Coke','Diet Coke','Sprite','Fanta','Root Beer','Lemonade','Iced Tea'];
        $drinkGids=[];
        foreach($drinks as $n){
            $drinkGids[]=$this->giRepo->create([
                'item_id'=>$this->prodRepo->create(['name'=>$n,'price'=>8]),
                'item_type'=>ItemType::PRODUCT,
            ]);
        }
        $drinkGroupId=$this->pgRepo->create([
            'name'=>'Drinks','type'=>ItemType::PRODUCT,'group_item_ids'=>$drinkGids,
        ]);

        /* 5) Wellness */
        $wellLvls=['Rare','Medium Rare','Medium','Medium Well','Well Done'];
        $wellGids=[];
        foreach($wellLvls as $n){
            $wellGids[]=$this->giRepo->create([
                'item_id'=>$this->ingRepo->create(['name'=>$n,'price'=>0]),
                'item_type'=>ItemType::INGREDIENT,
            ]);
        }
        $wellGroupId=$this->pgRepo->create([
            'name'=>'Cooking Preference','type'=>ItemType::INGREDIENT,'group_item_ids'=>$wellGids,
        ]);

        /* ---------- Assertions ---------- */

        /* Free group */
        $free   =$this->pgRepo->get($freeGroupId)->getResolvedItems($this->giRepo,$this->prodRepo,$this->ingRepo);
        // $this->dump('Free ingredients',$free);

        $this->assertCount(count($freeNames),$free);
        $this->assertContainsOnlyInstancesOf(\Ingredient::class,$free);
        $this->assertSame($freeNames,array_map(fn($i)=>$i->name,$free));
        $this->assertContainsOnly(IsType::TYPE_FLOAT,array_map(fn($i)=>$i->price,$free));
        $this->assertSame(0.0,max(array_map(fn($i)=>$i->price,$free)));

        /* Paid add-ons */
        $paid=$this->pgRepo->get($paidGroupId)->getResolvedItems($this->giRepo,$this->prodRepo,$this->ingRepo);
        // $this->dump('Paid add-ons',$paid);

        $bacon=array_values(array_filter($paid,fn($p)=>$p->name==='Bacon'))[0];
        $this->assertSame(6.5,$bacon->price);
        $this->assertSame(5.0,$paidAddons['Bacon']);

        /* Sides & drinks count check */
        $this->assertCount(count($sides),
            $this->pgRepo->get($sideGroupId)->getResolvedItems($this->giRepo,$this->prodRepo,$this->ingRepo));
        $this->assertCount(count($drinks),
            $this->pgRepo->get($drinkGroupId)->getResolvedItems($this->giRepo,$this->prodRepo,$this->ingRepo));

        /* Wellness prices zero */
        $well=$this->pgRepo->get($wellGroupId)->getResolvedItems($this->giRepo,$this->prodRepo,$this->ingRepo);
        // $this->dump('Wellness levels',$well);

        foreach($well as $w){
            $this->assertSame(0.0,$w->price,"Wellness {$w->name} price non-zero");
        }


        /* 2 – now create the burger and link the groups in the same call */
        $burgerId = $this->prodRepo->create([
            'name'              => 'Hamburger',
            'price'             => 29.0,
            'description'       => 'Our signature burger, crafted with a juicy, ...',
            'product_group_ids' => [
                $freeGroupId,
                $paidGroupId,
                $sideGroupId,
                $drinkGroupId,
                $wellGroupId,
            ],
        ]);



        /* 3 – verify */
        $burger = $this->prodRepo->get($burgerId);
        $full   = $burger->buildProduct();
        fwrite(STDERR,
            "\n=== 🍔  BURGER DEBUG DUMP ===\n" .
            print_r($full, true) . PHP_EOL
        );

        $this->assertSame(
            [$freeGroupId,$paidGroupId,$sideGroupId,$drinkGroupId,$wellGroupId],
            $burger->product_group_ids
        );

    }
}
