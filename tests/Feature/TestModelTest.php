<?php

class TestModelTest extends TestCase
{
    use RunMigrationsTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->runMigrations();
    }

    /**
     * Test the constructor
     *
     * @test
     */
    public function testModel()
    {
        $data = [
            'type' => 'test',
            'name' => 'Test',
        ];
        $rawData = array_merge([], $data, [
            'slug' => str_slug($data['name']),
        ]);
        $model = new TestModel();
        $model->data = $data;
        $model->save();

        $this->assertEquals($rawData, $model->data);
    }

    /**
     * Test the constructor
     *
     * @test
     * @expectedException \Folklore\EloquentJsonSchema\ValidationException
     */
    public function testModelException()
    {
        $data = [
            'type' => 'test',
            'name' => 1,
        ];
        $model = new TestModel();
        $model->data = $data;
        $model->save();
    }

    /**
     * Test relation
     *
     * @test
     */
    public function testModelChildren()
    {
        $childData = [
            'name' => 'Child',
        ];
        $child = new TestChildModel();
        $child->data = $childData;
        $child->save();

        // Add children
        $data = [
            'type' => 'test',
            'name' => 'Test',
            'children' => [$child]
        ];
        $rawData = array_merge([], $data, [
            'children' => [(string)$child->id],
            'slug' => str_slug($data['name']),
        ]);
        $model = new TestModel();
        $model->data = $data;
        $model->save();
        $model->load('children');

        $this->assertEquals($child->id, $model->data['children'][0]->id);
        $this->assertEquals($child->id, $model->children[0]->id);
        $this->assertEquals($rawData, array_only(json_decode($model->getAttributes()['data'], true), array_keys($rawData)));

        // Remove children
        $data = [
            'type' => 'test',
            'name' => 'Test',
            'children' => []
        ];
        $rawData = array_merge([], $data, [
            'slug' => str_slug($data['name']),
        ]);
        $model->data = $data;
        $model->save();
        $model->load('children');
        $this->assertEquals(0, sizeof($model->data['children']));
        $this->assertEquals(0, sizeof($model->children));
        $this->assertEquals($rawData, array_only(json_decode($model->getAttributes()['data'], true), array_keys($rawData)));
    }

    /**
     * Test relation with pivot
     *
     * @test
     */
    public function testModelChildreenWithPivot()
    {
        $childData = [
            'name' => 'Child',
        ];
        $child = new TestChildModel();
        $child->data = $childData;
        $child->save();

        // Add children
        $data = [
            'type' => 'test',
            'name' => 'Test',
            'childrenWithPivot' => [$child]
        ];
        $rawData = array_merge([], $data, [
            'childrenWithPivot' => [(string)$child->id],
            'slug' => str_slug($data['name']),
        ]);
        $model = new TestModel();
        $model->data = $data;
        $model->save();
        $model->load('childrenWithPivot');

        $this->assertEquals($child->id, $model->data['childrenWithPivot'][0]->id);
        $this->assertEquals($child->id, $model->childrenWithPivot[0]->id);
        $this->assertEquals($rawData, array_only(json_decode($model->getAttributes()['data'], true), array_keys($rawData)));

        // Remove children
        $data = [
            'type' => 'test',
            'name' => 'Test',
            'childrenWithPivot' => []
        ];
        $rawData = array_merge([], $data, [
            'slug' => str_slug($data['name']),
        ]);
        $model = new TestModel();
        $model->data = $data;
        $model->save();
        $model->load('childrenWithPivot');
        $this->assertEquals(0, sizeof($model->data['childrenWithPivot']));
        $this->assertEquals(0, sizeof($model->childrenWithPivot));
        $this->assertEquals($rawData, array_only(json_decode($model->getAttributes()['data'], true), array_keys($rawData)));
    }
}
