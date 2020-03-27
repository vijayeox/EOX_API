import React from 'react'
import DatePicker from 'react-datepicker'
import { Form, Row, Col, Button } from 'react-bootstrap'
import "react-datepicker/dist/react-datepicker.css";
import Select from 'react-select/creatable'

const FilterFields = function (props) {
    const { filters, index, fieldType, dataType, onUpdate, removeField,fieldName } = props;
    const filtersOptions = {
        "dateoptions": [{ "Between": "gte&&lte" }, { "<": "lt" }, { ">": "gt" }, { "=": "eq" }],
        "textoptions": [{ "Starts With": "startswith" }, { "contains": "contains" }],
        "numericoptions": [{ "<": "lt" }, { ">": "gt" }, { "=": "eq" }]
    };
    const dataTypeOptions = [
        "numeric"
    ]
    return (
        <Form.Row>
            <Col sm="2">
                <Form.Group  >
                    <Form.Label>Field Name</Form.Label>
                    <Form.Control type="text" name="fieldName" value={fieldName} onChange={(e) => onUpdate(e, index)} />
                </Form.Group>
            </Col>
            <Col sm="2">
                <Form.Group  >
                    <Form.Label>Data Type</Form.Label>
                    <Form.Control type="text" value={fieldType} disabled />
                </Form.Group>
            </Col>
            {
                dataType !== "date" && dataType !== "text"
                    ?
                    <Col sm="2">
                        <Form.Group controlId="formGridData">
                            <Form.Label>Data Type</Form.Label>
                            <Form.Control name="dataType" as="select" onChange={(e) => onUpdate(e, index)} value={filters[index] !== undefined ? filters[index]["dataType"] : ""} >

                                <option disabled key="-1" value=""></option>
                                {dataTypeOptions.map((item,index) => {
                                    return (<option key={index}>{item}</option>)
                                })}
                            </Form.Control>
                        </Form.Group>
                    </Col>
                    : null
            }
            <Col sm="2">
                <Form.Group >
                    <Form.Label>Options</Form.Label>
                    {
                        dataType === "date" || dataType === "text" || dataType === "numeric"
                            ?
                            <Form.Control as="select" name={"options"} onChange={(e) => onUpdate(e, index)} value={filters[index] !== undefined ? filters[index]["options"] : ""}>
                                <option disabled key="-1" value=""></option>
                                {filtersOptions[dataType + 'options'].map((item,index) => {
                                    return (<option key={index} value={Object.values(item)[0]}>{Object.keys(item)[0]}</option>)
                                })}
                            </Form.Control>
                            :
                            <Form.Control as="select" name={"options"} onChange={(e) => onUpdate(e, index)} value={filters[index] !== undefined ? filters[index]["options"] : ""}>
                                <option disabled key="-1" value=""></option>
                            </Form.Control>
                    }

                </Form.Group>
            </Col>
            <Col sm>
                <Form.Group controlId="formGridPassword">
                    <Form.Label>Default Value</Form.Label><br />
                    {dataType === "date"
                        ?
                        filters[index]["options"] !== "gte&&lte" ?
                            <DatePicker
                                key={index}
                                dateFormat="dd/MM/yyyy"
                                selected={filters[index]["startDate"]}
                                showMonthDropdown
                                showYearDropdown
                                dropdownMode="select"
                                popperPlacement="bottom"
                                popperModifiers={{
                                    flip: {
                                        behavior: ["bottom"] // don't allow it to flip to be above
                                    },
                                    preventOverflow: {
                                        enabled: false // tell it not to try to stay within the view (this prevents the popper from covering the element you clicked)
                                    },
                                    hide: {
                                        enabled: false // turn off since needs preventOverflow to be enabled
                                    }
                                }}
                                onChange={date => onUpdate(date, index, "startDate")}
                                name="startDate" />
                            :
                            <div className="dates-container">
                                <DatePicker
                                    selected={filters[index]["startDate"]}
                                    dateFormat="dd/MM/yyyy"
                                    onChange={date => onUpdate(date, index, "startDate")}
                                    selectsStart
                                    startDate={filters[index]["startDate"]}
                                    endDate={filters[index]["endDate"]}
                                    showMonthDropdown
                                    showYearDropdown
                                    popperPlacement="bottom"
                                    popperModifiers={{
                                        flip: {
                                            behavior: ["bottom"] // don't allow it to flip to be above
                                        },
                                        preventOverflow: {
                                            enabled: false // tell it not to try to stay within the view (this prevents the popper from covering the element you clicked)
                                        },
                                        hide: {
                                            enabled: false // turn off since needs preventOverflow to be enabled
                                        }
                                    }}
                                    dropdownMode="select"
                                />
                                <DatePicker
                                    selected={filters[index]["endDate"]}
                                    dateFormat="dd/MM/yyyy"
                                    onChange={date => onUpdate(date, index, "endDate")}
                                    selectsEnd
                                    startDate={filters[index]["startDate"]}
                                    endDate={filters[index]["endDate"]}
                                    minDate={filters[index]["startDate"]}
                                    showMonthDropdown
                                    showYearDropdown
                                    popperPlacement="bottom"
                                    popperModifiers={{
                                        flip: {
                                            behavior: ["bottom"] // don't allow it to flip to be above
                                        },
                                        preventOverflow: {
                                            enabled: false // tell it not to try to stay within the view (this prevents the popper from covering the element you clicked)
                                        },
                                        hide: {
                                            enabled: false // turn off since needs preventOverflow to be enabled
                                        }
                                    }}
                                    dropdownMode="select"
                                />
                            </div>
                        :
                        <Form.Control type="text" name="defaultvalue" onChange={(e) => onUpdate(e, index)} value={filters[index] !== undefined ? filters[index]["defaultvalue"] : ""} />}
                </Form.Group>
            </Col>
            <Col style={{ marginBottom: "1em" }}>
                <Form.Group>
                    <Button onClick={(e) => removeField(index, fieldType)}>x</Button>
                </Form.Group>
            </Col>
        </Form.Row>)
}


class DashboardEditorFilter extends React.Component {
    constructor(props) {
        super(props);
        this.handleChange = this.handleChange.bind(this);
        this.handleSelect = this.handleSelect.bind(this);
        this.createField = this.createField.bind(this);
        this.removeField = this.removeField.bind(this);

        this.state = {
            input: {},
            focused: null,
            inputFields: [],
            startDate: new Date(),
            availableFilterOption: [{ value: "text", label: "Text" }, { value: "date", label: "Date" }],
            filters: []
        }
    }

    removeField(index, field) {
        var availableOptions = [...this.state.availableFilterOption];
        let filters = [...this.state.filters]

        if (index > -1) {
            delete filters[index]
            //add back the option to the filter list
            // if (field === "text" || field === "date") {
            //     let newItem = { value: field, label: field }
            //     availableOptions.push(newItem)
            //     this.setState({ filters: filters, availableFilterOption: availableOptions })
            // }
            // else {
                this.setState({ filters: filters })
            // }
        }
    }

    createField(fieldType) {
        var availableOptions = [...this.state.availableFilterOption];
        let filters = [...this.state.filters]
        let newoption = null
        let length = filters !== undefined ? filters.length : 0
        if (fieldType === "date") {
            filters.push({ fieldName:'',fieldType: fieldType, dataType: "date", options: "", value: new Date(), key: length })
        } else if (fieldType === "text") {
            filters.push({ fieldName:'',fieldType: fieldType, dataType: "text", options: "", value: "", key: length })
        } else {
            filters.push({ fieldName:'',fieldType: fieldType, dataType: "", options: "", value: "", key: length })
        }
        //removing filter from option list 
        // newoption = availableOptions.filter(function (obj) {
        //     return obj.value !== fieldname;
        // });
        // this.setState({ availableFilterOption: newoption, filters: filters })
        
        this.setState({  filters: filters })

    }

    handleChange(e) {
        let name = e.target.name;
        let value = e.target.value;
        this.setState({ input: { ...this.state.input, [name]: value } })
    }

    updateFilterRow(e, index, type) {
        let name
        let value
        if (type === "startDate" || type === "endDate") {
            name = type
            value = e
        }
        else {
            name = e.target.name
            value = e.target.value
        }
        let filters = [...this.state.filters]
        filters[index][name] = value
        this.setState({ filters })
    }
    handleSelect(e) {
        let name = e.value;
        let value = e.label;
        if (e.__isNew__) {
            let filters = [...this.state.filters]
            let length = filters !== undefined ? filters.length : 0
            filters.push({ fieldName:'',fieldType: name, dataType: "", options: "", value: "", key: length })
            this.setState({ input: { ...this.state.input, "filtertype": "" }, filters })
        } else {
            this.createField(e.value)
            this.setState({ input: { ...this.state.input, "filtertype": "" } })
        }
    }

    hideFilterDiv() {
        var element = document.getElementById("filter-form-container");
        element.classList.add("disappear");
        document.getElementById("dashboard-container").classList.remove("disappear")
        document.getElementById("dashboard-filter-btn").disabled = false

    }

    saveFilter() {

        let restClient = this.props.core.make('oxzion/restClient');
        let filters
        if (this.state.filters !== undefined) {
            filters = this.state.filters.filter(function (obj) {
                return obj !== undefined && obj.value !== undefined;
            });
        }

        let formData = {}
        formData["dashboard_type"] = "html";
        formData["version"] = this.props.dashboardVersion;
        formData["filters"] = filters

        console.log(formData)
        //uncomment once api is implemented

        // formData["filters"]="";
        // this.restClient.request(
        //     "v1",
        //     'analytics/dashboard/' + this.props.dashboardId,
        //     formData,
        //     "put"
        //   )
        //     .then(response => {
        //       props.refreshGrid.current.child.current.refresh()
        //       notify(response, operation)
        //       props.resetInput()
        //       props.onHide()
        //     })
        //     .catch(err => {
        //       console.log(err)
        //     })
    }
    render() {
        return (
            <div id="filter-form-container" className="disappear">
                <Row className="pull-right">
                    <button type="button" className="close" aria-label="Close" onClick={() => this.hideFilterDiv()}>
                        <span aria-hidden="true">&times;</span>
                    </button>
                </Row>
                <Form className="create-filter-form">
                    {this.state.filters.filter(obj => obj !== undefined).map((filterRow, index) => {
                        return <FilterFields
                            index={filterRow.key}
                            filters={this.state.filters}
                            input={this.state.input}
                            key={index}
                            dataType={filterRow.dataType || ""}
                            value={filterRow.value || this.state.input[filterRow.fieldType + "defaultvalue"]}
                            removeField={this.removeField}
                            fieldType={filterRow.fieldType}
                            fieldName={filterRow.fieldName}
                            onUpdate={(e, index, type) => this.updateFilterRow(e, index, type)}
                            onChange={(e) => this.handleChange(e)}
                        />
                    })
                    }
                    <Form.Group>
                        <Form.Label> Choose/Create Filters </Form.Label>
                        <Select
                            placeholder="Choose filters"
                            name="filtertype"
                            id="filtertype"
                            onChange={(e) => this.handleSelect(e)}
                            value={this.state.input["filtertype"]}
                            options={this.state.availableFilterOption}
                        />
                    </Form.Group>
                    <Row >
                        <Button className="apply-filter-btn" onClick={() => this.saveFilter()}>Apply Filters</Button>

                    </Row>

                </Form>
            </div>
        )
    }
}
export default DashboardEditorFilter