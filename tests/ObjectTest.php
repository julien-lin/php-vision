<?php

declare(strict_types=1);

namespace JulienLinard\Vision\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Vision\Vision;
use JulienLinard\Vision\Runtime\VariableResolver;

class ObjectTest extends TestCase
{
    private Vision $vision;

    protected function setUp(): void
    {
        $this->vision = new Vision();
    }

    public function testObjectWithPublicProperty(): void
    {
        $object = new class {
            public string $name = 'John';
        };

        $template = '{{ user.name }}';
        $result = $this->vision->renderString($template, ['user' => $object]);

        $this->assertEquals('John', $result);
    }

    public function testObjectWithGetter(): void
    {
        $object = new class {
            public function getName(): string
            {
                return 'Jane';
            }
        };

        $template = '{{ user.name }}';
        $result = $this->vision->renderString($template, ['user' => $object]);

        $this->assertEquals('Jane', $result);
    }

    public function testObjectWithMagicGet(): void
    {
        $object = new class {
            private array $data = ['name' => 'Bob'];

            public function __get(string $name): mixed
            {
                return $this->data[$name] ?? null;
            }
        };

        $template = '{{ user.name }}';
        $result = $this->vision->renderString($template, ['user' => $object]);

        $this->assertEquals('Bob', $result);
    }

    public function testNestedObjectProperties(): void
    {
        $object = new class {
            public object $profile;

            public function __construct()
            {
                $this->profile = new class {
                    public string $email = 'test@example.com';
                };
            }
        };

        $template = '{{ user.profile.email }}';
        $result = $this->vision->renderString($template, ['user' => $object]);

        $this->assertEquals('test@example.com', $result);
    }

    public function testObjectInLoop(): void
    {
        $objects = [
            new class {
                public string $name = 'User1';
            },
            new class {
                public string $name = 'User2';
            },
        ];

        $template = '{% for user in users %}{{ user.name }}{% endfor %}';
        $result = $this->vision->renderString($template, ['users' => $objects]);

        $this->assertStringContainsString('User1', $result);
        $this->assertStringContainsString('User2', $result);
    }

    public function testObjectWithGetterInCondition(): void
    {
        $object = new class {
            public function isActive(): bool
            {
                return true;
            }
        };

        $template = '{% if user.active %}Active{% else %}Inactive{% endif %}';
        $result = $this->vision->renderString($template, ['user' => $object]);

        // Le getter isActive() devrait être appelé pour 'active'
        $this->assertStringContainsString('Active', $result);
    }

    /**
     * Test que le cache de reflection fonctionne correctement
     * Ceci vérifie l'optimisation de performance qui évite les réflexions répétitives
     */
    public function testReflectionCache(): void
    {
        $resolver = new VariableResolver();
        
        // Objet avec getter
        $object1 = new class {
            public function getName(): string
            {
                return 'Test1';
            }
        };
        
        // Objet avec propriété publique
        $object2 = new class {
            public string $name = 'Test2';
        };
        
        // Objet avec propriété privée (nécessite reflection)
        $object3 = new class {
            private string $name = 'Test3';
            
            public function getName(): string
            {
                return $this->name;
            }
        };
        
        // Premier accès - doit résoudre et mettre en cache
        $result1 = $resolver->resolve(['obj' => $object1], 'obj.name');
        $this->assertEquals('Test1', $result1);
        
        // Deuxième accès - doit utiliser le cache
        $result2 = $resolver->resolve(['obj' => $object1], 'obj.name');
        $this->assertEquals('Test1', $result2);
        
        // Test avec propriété publique
        $result3 = $resolver->resolve(['obj' => $object2], 'obj.name');
        $this->assertEquals('Test2', $result3);
        
        // Test avec getter isXxx
        $object4 = new class {
            public function isActive(): bool
            {
                return true;
            }
        };
        
        $result4 = $resolver->resolve(['obj' => $object4], 'obj.active');
        $this->assertTrue($result4);
        
        // Deuxième accès - doit utiliser le cache
        $result5 = $resolver->resolve(['obj' => $object4], 'obj.active');
        $this->assertTrue($result5);
        
        // Test nettoyage du cache
        $resolver->clearReflectionCache();
        
        // Après nettoyage, le cache devrait être vide mais la résolution devrait toujours fonctionner
        $result6 = $resolver->resolve(['obj' => $object1], 'obj.name');
        $this->assertEquals('Test1', $result6);
    }
}
