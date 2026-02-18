-- Expande ENUM de roles para incluir garcom e cozinha
ALTER TABLE users MODIFY COLUMN role ENUM('admin','staff','garcom','cozinha') NOT NULL DEFAULT 'garcom';

-- Migra usuários staff existentes para garcom
UPDATE users SET role = 'garcom' WHERE role = 'staff';