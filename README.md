# Project Structure
Ingredient and Products are the main structrures
GroupItem is an abstractation of Ingredient/Product
ProductGroup is a bunch of GroupItems (Ingredient/Product)
Product can have multiple ProductGroups
Override of price can be on GroupItem
Repository files are handling all access to database for their corrosponding models and the RepostioryInterface sets the baseline for them

