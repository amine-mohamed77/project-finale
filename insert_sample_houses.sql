-- Insert sample houses into the house table

-- Make sure to adjust the IDs based on your existing data
-- These inserts assume that:
-- - city_id values exist in your city table
-- - proprety_type_id values exist in your proprety_type table
-- - owner_id values exist in your owner table

INSERT INTO house (house_id, city_id, proprety_type_id, owner_id, house_title, house_price, house_location, house_badroom, house_bathroom, house_description) VALUES
(1, 33, 2, 1, 'Modern Studio Apartment Near University', 500, 'Agadir, University District, 123 Student Ave', 1, 1, 'A beautiful modern studio apartment perfect for students. Features include high-speed internet, fully furnished with modern appliances, and walking distance to campus.'),
(2, 4, 3, 1, 'Spacious Family House with Garden', 1200, 'Casablanca, Residential Area, 456 Family Street', 3, 2, 'Large family house with a beautiful garden. Includes 3 bedrooms, 2 bathrooms, a fully equipped kitchen, and a spacious living room. Close to schools and shopping centers.'),
(3, 11, 4, 2, 'Cozy Room in Shared Apartment', 300, 'Meknès, City Center, 789 Roommate Road', 1, 1, 'Comfortable private room in a shared apartment. Includes access to common areas, kitchen, and bathroom. Utilities included in the price. Perfect for students.'),
(4, 33, 5, 2, 'University Dormitory - Single Room', 250, 'Agadir, Campus Area, University Residence Hall', 1, 1, 'Single room in university dormitory. Shared bathroom and kitchen facilities. All utilities included. On-campus location with easy access to classes, library, and student center.'),
(5, 4, 2, 3, 'Luxury Apartment with Ocean View', 800, 'Casablanca, Coastal Area, 101 Ocean Drive', 2, 2, 'Stunning apartment with panoramic ocean views. Features 2 bedrooms, 2 bathrooms, modern kitchen, and a spacious balcony. Building includes gym and swimming pool.'),
(6, 11, 3, 3, 'Charming Traditional House in Old City', 700, 'Meknès, Historic District, 202 Heritage Lane', 2, 1, 'Beautiful traditional house in the heart of the old city. Features authentic architecture, 2 bedrooms, 1 bathroom, and a private courtyard. Walking distance to historical sites.'),
(7, 33, 4, 1, 'Student Room with Private Bathroom', 350, 'Agadir, Student Quarter, 303 Scholar Street', 1, 1, 'Private room with en-suite bathroom in a student residence. Includes desk, bed, wardrobe, and high-speed internet. Common kitchen and laundry facilities available.'),
(8, 4, 5, 2, 'Modern Dormitory with Study Areas', 280, 'Casablanca, University Zone, 404 Academic Avenue', 1, 1, 'Modern dormitory room with access to dedicated study areas, common rooms, and recreational facilities. All utilities and internet included. 24/7 security and reception.'),
(9, 11, 2, 3, 'Newly Renovated Apartment for Students', 450, 'Meknès, Central District, 505 Student Boulevard', 2, 1, 'Freshly renovated apartment ideal for student sharing. Features 2 bedrooms, 1 bathroom, fully equipped kitchen, and living area. Close to universities and public transportation.'),
(10, 33, 3, 1, 'Family-Friendly House with Yard', 900, 'Agadir, Suburban Area, 606 Family Circle', 4, 2, 'Spacious family house with 4 bedrooms, 2 bathrooms, large kitchen, and private yard. Quiet neighborhood with easy access to schools, parks, and shopping centers.');
