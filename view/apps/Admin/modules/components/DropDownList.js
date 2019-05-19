import React from "react";
import { DropDownList } from '@progress/kendo-react-dropdowns';
import { filterBy } from '@progress/kendo-data-query';
import { GetData, GetData2 } from "../components/apiCalls";

import withValueField from '../dialog/withValueField';
const DropDownListWithValueField = withValueField(DropDownList);

export class DropDown extends React.Component {
    constructor(props) {
        super(props);
        this.core = this.props.args;
        this.masterUserList = [];
        this.state = {
            mainList: []
        };
        let loader = this.core.make("oxzion/splash");
        loader.show();
        if (this.props.mainList == "organization" || this.props.mainList == "user") {
            GetData2(this.props.mainList).then(response => {
                var tempUsers = [];
                for (var i = 0; i <= response.data.length - 1; i++) {
                    var userName = response.data[i].name;
                    var userid = response.data[i].id;
                    tempUsers.push({ id: userid, name: userName });
                }
                this.setState({
                    mainList: tempUsers
                });
                this.masterUserList = tempUsers;
                let loader = this.core.make("oxzion/splash");
                loader.destroy();
            });
        } else {
            GetData(this.props.mainList).then(response => {
                var tempUsers = [];
                for (var i = 0; i <= response.data.length - 1; i++) {
                    var userName = response.data[i].name;
                    var userid = response.data[i].id;
                    tempUsers.push({ id: userid, name: userName });
                }
                this.setState({
                    mainList: tempUsers
                });
                this.masterUserList = tempUsers;
                let loader = this.core.make("oxzion/splash");
                loader.destroy();
            });
        }
    }

    filterChange = (event) => {
        this.setState({
            mainList: this.filterData(event.filter)
        });
    }

    filterData(filter) {
        const data = this.masterUserList.slice();
        return filterBy(data, filter);
    }
    render() {
        return (
            <DropDownListWithValueField
                data={this.state.mainList}
                textField="name"
                value={this.props.selectedItem}
                valueField="id"
                onChange={this.props.onDataChange}
                filterable={true}
                onFilterChange={this.filterChange}
                style={{ width: "210px" }}
                popupSettings={{ height: "160px" }}
            />
        )
    }
}