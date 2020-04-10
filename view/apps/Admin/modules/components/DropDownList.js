import React from "react";
import { DropDownList } from "@progress/kendo-react-dropdowns";
import { filterBy } from "@progress/kendo-data-query";
import { GetDataSearch } from "../components/apiCalls";
import withValueField from "./filterForStaticDropdown";
const DropDownListWithValueField = withValueField(DropDownList);

export default class DropDown extends React.Component {
  constructor(props) {
    super(props);
    this.core = this.props.args;
    this.masterUserList = [];
    this.state = {
      mainList: [],
      selectedItem: this.props.selectedItem
    };
    this.timeout = null;
  }

  UNSAFE_componentWillMount() {
    if (typeof this.props.rawData == "object") {
      this.setState({
        mainList: this.props.rawData
      });
      this.masterUserList = this.props.rawData;
    } else {
      if (this.props.preFetch) {
        let loader = this.core.make("oxzion/splash");
        loader.show();
        this.getMainList(null, 20, true);
      }
    }
  }

  componentDidUpdate(prevProps) {
    if (this.props.rawData !== prevProps.rawData) {
      this.setState({
        mainList: this.props.rawData
      });
      this.masterUserList = this.props.rawData;
    }
    if (this.props.selectedItem !== prevProps.selectedItem) {
      this.setState({
        selectedItem: this.props.selectedItem
      });
    }
  }

  getMainList = (query, size) => {
    GetDataSearch(this.props.mainList, query, size).then((response) => {
      var tempUsers = [];
      for (var i = 0; i <= response.data.length - 1; i++) {
        var userName = response.data[i].name;
        var userid = response.data[i].uuid;
        tempUsers.push({ id: userid, name: userName });
      }
      this.setState({
        mainList: tempUsers
      });
      let loader = this.core.make("oxzion/splash");
      loader.destroy();
    });
  };

  filterChangeAPI = (e) => {
    clearTimeout(this.timeout);
    this.timeout = setTimeout(() => {
      this.getMainList(e.filter.value, 20);
    }, 1000);
  };

  filterChange = (event) => {
    this.setState({
      mainList: this.filterData(event.filter)
    });
  };

  filterData(filter) {
    const data = this.masterUserList.slice();
    return filterBy(data, filter);
  }

  render() {
    this.props.disableItem ? (this.inputProps = { opened: false }) : [];
    return (
      <div>
        {this.props.rawData ? (
          <DropDownList
            data={this.state.mainList}
            {...this.inputProps}
            value={this.state.selectedItem}
            onChange={this.props.onDataChange}
            textField={this.props.keyValuePair ? "name" : undefined}
            valueField={this.props.keyValuePair ? "id" : undefined}
            filterable={true}
            onFilterChange={this.filterChange}
            style={{ width: this.props.width ? this.props.width : "100%" }}
            popupSettings={{ height: "160px" }}
            validationMessage={this.props.validationMessage}
            required={this.props.required}
          />
        ) : (
          <DropDownListWithValueField
            data={this.state.mainList}
            {...this.inputProps}
            textField={"name"}
            valueField={"id"}
            value={this.state.selectedItem}
            onChange={this.props.onDataChange}
            filterable={true}
            onFilterChange={this.filterChangeAPI}
            style={{ width: "100%" }}
            popupSettings={{ height: "160px" }}
            validationMessage={this.props.validationMessage}
            required={this.props.required}
          />
        )}
      </div>
    );
  }
}
