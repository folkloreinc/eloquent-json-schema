<?php

class EloquentJsonSchemaTest extends TestCase
{
    use RunMigrationsTrait;

    public function setUp()
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
        $dataWithSlug = array_merge([], $data, [
            'slug' => str_slug($data['name']),
        ]);
        $model = new TestModel();
        $model->data = $data;
        $model->save();

        $this->assertEquals($model->data, $dataWithSlug);
        $this->assertEquals($model->getAttributes()['data'], json_encode($dataWithSlug));
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
     * Test the constructor
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

        $data = [
            'type' => 'test',
            'name' => 'Test',
            'children' => [$child],
            'child' => $child
        ];
        $model = new TestModel();
        $model->data = $data;
        $model->save();
        $model->load('children');

        $this->assertEquals($model->data['child']->id, $child->id);
        $this->assertEquals($model->data['children'][0]->id, $child->id);
    }
}
