-- SQL file to update the messages table to include image_url column

ALTER TABLE `messages` 
ADD COLUMN `image_url` VARCHAR(255) DEFAULT NULL AFTER `message_text`;

-- Update the primary key if it doesn't exist
ALTER TABLE `messages` 
ADD PRIMARY KEY (`message_id`);

-- Set auto increment if not already set
ALTER TABLE `messages` 
MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;
