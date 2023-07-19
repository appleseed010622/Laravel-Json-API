<?php
/*
 * Copyright 2023 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Laravel\Tests\Acceptance\Relationships;

use App\JsonApi\V1\Posts\PostSchema;
use App\Models\Post;
use App\Models\Tag;
use Closure;
use LaravelJsonApi\Core\Facades\JsonApi;
use LaravelJsonApi\Laravel\Facades\JsonApiRoute;
use LaravelJsonApi\Laravel\Http\Controllers\JsonApiController;
use LaravelJsonApi\Laravel\Tests\Acceptance\TestCase;
use function url;

class ToManyLinksTest extends TestCase
{

    /**
     * @var PostSchema
     */
    private PostSchema $schema;

    /**
     * @var Post
     */
    private Post $post;

    /**
     * @var Tag
     */
    private Tag $tag;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        JsonApiRoute::server('v1')->prefix('api/v1')->resources(function ($server) {
            $server->resource('posts', JsonApiController::class)->relationships(function ($relationships) {
                $relationships->hasMany('tags');
            });
        });

        $this->schema = JsonApi::server('v1')->schemas()->schemaFor('posts');
        $this->post = Post::factory()->create();
        $this->post->tags()->attach($this->tag = Tag::factory()->create());
    }

    /**
     * @return array[]
     */
    public static function scenarioProvider(): array
    {
        return [
            'hidden' => [
                static function (PostSchema $schema) {
                    $schema->relationship('tags')->hidden();
                    return null;
                },
            ],
            'no links' => [
                static function (PostSchema $schema) {
                    $schema->relationship('tags')->serializeUsing(
                        static fn($relation) => $relation->withoutLinks()
                    );
                    return null;
                },
            ],
            'no self link' => [
                static function (PostSchema $schema, Post $post) {
                    $schema->relationship('tags')->serializeUsing(
                        static fn($relation) => $relation->withoutSelfLink()
                    );
                    return ['related' => url('/api/v1/posts', [$post, 'tags'])];
                },
            ],
            'no related link' => [
                static function (PostSchema $schema, Post $post) {
                    $schema->relationship('tags')->serializeUsing(
                        static fn($relation) => $relation->withoutRelatedLink()
                    );
                    return ['self' => url('/api/v1/posts', [$post, 'relationships', 'tags'])];
                },
            ],
        ];
    }

    /**
     * @param Closure $scenario
     * @return void
     * @dataProvider scenarioProvider
     */
    public function testRelated(Closure $scenario): void
    {
        $expected = $scenario($this->schema, $this->post);

        $response = $this
            ->withoutExceptionHandling()
            ->jsonApi('tags')
            ->get(url('/api/v1/posts', [$this->post, 'tags']));

        $response->assertFetchedMany([$this->tag]);

        if (is_array($expected)) {
            $response->assertLinks($expected);
        }
    }

    /**
     * @param Closure $scenario
     * @return void
     * @dataProvider scenarioProvider
     */
    public function testSelf(Closure $scenario): void
    {
        $expected = $scenario($this->schema, $this->post);

        $response = $this
            ->withoutExceptionHandling()
            ->jsonApi('tags')
            ->get(url('/api/v1/posts', [$this->post, 'relationships', 'tags']));

        $response->assertFetchedToMany([$this->tag]);

        if (is_array($expected)) {
            $response->assertLinks($expected);
        }
    }

}
