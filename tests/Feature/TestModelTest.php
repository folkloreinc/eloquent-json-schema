<?php

class TestModelTest extends TestCase
{
    use RunMigrationsTrait;
    
    protected function setUp()
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
        $this->assertEquals(json_encode($rawData), $model->getAttributes()['data']);
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
        $this->assertEquals('data.children.0', $model->children[0]->test_handle);
        $this->assertEquals(json_encode($rawData), $model->getAttributes()['data']);

        // Remove children
        $data = [
            'type' => 'test',
            'name' => 'Test',
            'children' => []
        ];
        $rawData = array_merge([], $data, [
            'slug' => str_slug($data['name']),
        ]);
        $model = new TestModel();
        $model->data = $data;
        $model->save();
        $model->load('children');
        $this->assertEquals(0, sizeof($model->data['children']));
        $this->assertEquals(0, sizeof($model->children));
        $this->assertEquals(json_encode($rawData), $model->getAttributes()['data']);
    }

    /**
     * Test relation with pivot
     *
     * @test
     */
    public function testModelChildrenWithPivot()
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
        $this->assertEquals('data.childrenWithPivot.0', $model->childrenWithPivot[0]->pivot->handle);
        $this->assertEquals(json_encode($rawData), $model->getAttributes()['data']);

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
        $this->assertEquals(json_encode($rawData), $model->getAttributes()['data']);
    }
}
