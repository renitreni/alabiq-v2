import { z } from 'zod';

// Common validation schemas
export const emailSchema = z.string().email('Invalid email address');

export const passwordSchema = z
    .string()
    .min(8, 'Password must be at least 8 characters')
    .regex(/[A-Z]/, 'Password must contain at least one uppercase letter')
    .regex(/[a-z]/, 'Password must contain at least one lowercase letter')
    .regex(/[0-9]/, 'Password must contain at least one number');

export const phoneSchema = z.string().regex(/^\+?[1-9]\d{1,14}$/, 'Invalid phone number');

// Address schemas
export const addressSchema = z.object({
    label: z.string().min(1, 'Label is required').max(50, 'Label too long'),
    street: z.string().min(1, 'Street address is required').max(200, 'Address too long'),
    building: z.string().max(50, 'Building name too long').optional(),
    floor: z.string().max(20, 'Floor too long').optional(),
    apartment: z.string().max(20, 'Apartment too long').optional(),
    landmark: z.string().max(200, 'Landmark too long').optional(),
    city: z.string().min(1, 'City is required').max(100, 'City too long'),
    district: z.string().max(100, 'District too long').optional(),
    postalCode: z.string().max(20, 'Postal code too long').optional(),
    coordinates: z.object({
        lat: z.number(),
        lng: z.number(),
    }).optional(),
    isDefault: z.boolean().default(false),
});

// Restaurant schemas
export const restaurantSchema = z.object({
    name: z.string().min(1, 'Name is required').max(100, 'Name too long'),
    slug: z.string().min(1, 'Slug is required').max(100, 'Slug too long').regex(/^[a-z0-9-]+$/, 'Slug must be lowercase alphanumeric with hyphens'),
    description: z.string().max(1000, 'Description too long').optional(),
    cuisineType: z.string().min(1, 'Cuisine type is required'),
    phone: phoneSchema,
    email: emailSchema,
    website: z.string().url('Invalid URL').optional().or(z.literal('')),
    address: z.string().min(1, 'Address is required').max(300, 'Address too long'),
    coordinates: z.object({
        lat: z.number(),
        lng: z.number(),
    }),
    deliveryFee: z.number().min(0, 'Delivery fee cannot be negative').default(0),
    minimumOrder: z.number().min(0, 'Minimum order cannot be negative').default(0),
    estimatedDeliveryTime: z.number().min(1, 'Must be at least 1 minute').max(120, 'Too long').default(30),
    isActive: z.boolean().default(true),
});

// Menu item schemas
export const menuItemOptionValueSchema = z.object({
    name: z.string().min(1, 'Name is required').max(50, 'Name too long'),
    price: z.number().min(0, 'Price cannot be negative').default(0),
    isDefault: z.boolean().default(false),
    sortOrder: z.number().int().min(0).default(0),
});

export const menuItemOptionSchema = z.object({
    name: z.string().min(1, 'Name is required').max(50, 'Name too long'),
    type: z.enum(['single', 'multiple']),
    required: z.boolean().default(false),
    values: z.array(menuItemOptionValueSchema).min(1, 'At least one option value required'),
});

export const menuItemSchema = z.object({
    name: z.string().min(1, 'Name is required').max(100, 'Name too long'),
    description: z.string().max(500, 'Description too long').optional(),
    price: z.number().min(0, 'Price cannot be negative'),
    categoryId: z.string().uuid('Invalid category'),
    image: z.string().url('Invalid image URL').optional().or(z.literal('')),
    isAvailable: z.boolean().default(true),
    isPopular: z.boolean().default(false),
    isVegetarian: z.boolean().default(false),
    isVegan: z.boolean().default(false),
    isGlutenFree: z.boolean().default(false),
    spiceLevel: z.number().int().min(0).max(5).default(0),
    preparationTime: z.number().int().min(1).max(60).default(10),
    calories: z.number().int().min(0).optional(),
    allergens: z.array(z.string()).optional(),
    options: z.array(menuItemOptionSchema).optional(),
});

// Order schemas
export const cartItemSchema = z.object({
    menuItemId: z.string().uuid('Invalid menu item'),
    quantity: z.number().int().min(1, 'Quantity must be at least 1').max(99, 'Quantity too high'),
    options: z.record(z.string(), z.array(z.string())).optional(),
    specialInstructions: z.string().max(500, 'Instructions too long').optional(),
});

export const checkoutSchema = z.object({
    deliveryAddressId: z.string().uuid('Invalid address'),
    paymentMethodId: z.string().uuid('Invalid payment method').optional(),
    paymentMethod: z.enum(['card', 'cash', 'wallet']),
    tip: z.number().min(0, 'Tip cannot be negative').default(0),
    instructions: z.string().max(500, 'Instructions too long').optional(),
    scheduledFor: z.string().datetime().optional(),
});

// Auth schemas
export const loginSchema = z.object({
    email: emailSchema,
    password: z.string().min(1, 'Password is required'),
    remember: z.boolean().default(false),
});

export const registerSchema = z.object({
    name: z.string().min(2, 'Name must be at least 2 characters').max(100, 'Name too long'),
    email: emailSchema,
    password: passwordSchema,
    passwordConfirmation: z.string(),
    phone: phoneSchema.optional(),
    role: z.enum(['customer', 'restaurant', 'rider']).default('customer'),
}).superRefine((data, ctx) => {
    if (data.password !== data.passwordConfirmation) {
        ctx.addIssue({
            code: z.ZodIssueCode.custom,
            message: 'Passwords do not match',
            path: ['passwordConfirmation'],
        });
    }
});

// Export type inference helpers
export type AddressFormData = z.infer<typeof addressSchema>;
export type RestaurantFormData = z.infer<typeof restaurantSchema>;
export type MenuItemFormData = z.infer<typeof menuItemSchema>;
export type CartItemFormData = z.infer<typeof cartItemSchema>;
export type CheckoutFormData = z.infer<typeof checkoutSchema>;
export type LoginFormData = z.infer<typeof loginSchema>;
export type RegisterFormData = z.infer<typeof registerSchema>;