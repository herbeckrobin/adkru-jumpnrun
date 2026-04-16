import { z } from 'zod';

export const GameConfigSchema = z.object({
  canvasWidth: z.number().positive().default(960),
  canvasHeight: z.number().positive().default(540),
  /** Gravity added to velocityY each fixed step (px/frame at 60 fps) */
  gravity: z.number().default(1.8),
  /** Initial Y velocity on jump (negative = upward) */
  jumpVelocity: z.number().default(-22),
  /** Maximum jumps before landing (2 = double jump) */
  maxJumps: z.number().int().min(1).default(2),
  /** Base horizontal scroll speed of entities (px/frame) */
  baseSpeed: z.number().positive().default(5),
  /** Extra speed added per level */
  speedPerLevel: z.number().default(0.5),
  /** Background parallax base speed */
  bgScrollBase: z.number().default(2),
  /** Extra bg scroll speed per level */
  bgScrollPerLevel: z.number().default(0.4),
  /** Coins collected to advance one level */
  coinsPerLevel: z.number().int().positive().default(5),
  /** Maximum level number */
  maxLevels: z.number().int().positive().default(10),
  /** Gap between canvas bottom and ground surface (px) */
  groundOffset: z.number().default(60),
  /** Level at which the discount popup appears */
  discountLevel: z.number().int().positive().default(3),
  discountCode: z.string().default('BURNERKING20'),
  playerWidth: z.number().positive().default(70),
  playerHeight: z.number().positive().default(70),
  playerStartX: z.number().default(120),
});

export type GameConfig = z.infer<typeof GameConfigSchema>;
export const DEFAULT_CONFIG: GameConfig = GameConfigSchema.parse({});
