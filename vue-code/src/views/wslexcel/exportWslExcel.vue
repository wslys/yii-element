<template>
    <div class="app-container">
        <el-table
                :data="tableData"
                style="width: 100%">
            <el-table-column prop="username" label="用户名" width="180"></el-table-column>
            <el-table-column prop="phone" label="电话" width="180"></el-table-column>
            <el-table-column prop="type" label="类型"></el-table-column>
        </el-table>

        <el-pagination
                @size-change="handleSizeChange"
                @current-change="handleCurrentChange"
                :hide-on-single-page="value"
                :current-page="3"
                :page-sizes="[100, 200, 300, 400]"
                :page-size="100"
                layout="total, sizes, prev, pager, next, jumper"
                :total="9800">
        </el-pagination>

    </div>
</template>

<script>
    import { getList } from '@/api/user'
    export default {
        name: 'ExportWslExcel',
        components: {},
        data() {
            return {
                tableData: []
            }
        },
        created() {
            this.getList()
        },
        methods: {
            async getList() {
                const { data } = await getList()
                this.tableData = data
                console.log(data)
            },
        }
    }
</script>
