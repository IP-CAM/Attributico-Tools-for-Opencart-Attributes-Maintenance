import React, { useEffect, useState, useRef } from "react";
import styles from "./Autocomplete.css";

const Autocomplete = React.forwardRef((props, ref) => {
  const {
    name,
    label,
    placeholder,
    defaultValue,
    data
  } = props;
  const [display, setDisplay] = useState(false);
  const [options, setOptions] = useState([]);
  const [search, setSearch] = useState("");
  const [filter, setFilter] = useState(false);
  const wrapperRef = useRef(null);

  useEffect(() => {
    setSearch(defaultValue)
  }, []);

  /* useEffect(() => {    
    let response = []
    $.ajax({  .....
    Здесь делаем загрузку данных для последующего использования в выпадающем списке
  }, []); */

  useEffect(() => {
    window.addEventListener("mousedown", handleClickOutside);
    return () => {
      window.removeEventListener("mousedown", handleClickOutside);
    };
  });

  /* Если нужно отловить нажатие какой-то клавиши на каком-то тэге и обработать своей функцией */
  /* useEffect(() => {
    const listener = event => {
      let tag = event.target.tagName.toLowerCase();
      console.log('listener',event.target.tagName);
      if ((event.code === "Enter" || event.code === "NumpadEnter") && (tag === 'span' ) ||  tag === 'li') {
        console.log("Enter key was pressed. Run your function.", event.target.value);
        event.preventDefault();
        setSearch(event.target.value)
        setDisplay(false)
        // callMyFunction();
      }
    };
    document.addEventListener("keydown", listener);
    return () => {
      document.removeEventListener("keydown", listener);
    };
  }, []);   */

  const handleClickOutside = event => {
    const { current: wrap } = wrapperRef;
    if (wrap && !wrap.contains(event.target)) {
      setDisplay(false);
    }
  };

  const handleOnChange = (event) => {
    setSearch(event.target.value)
    setFilter(true)
  };

  const handleSelect = (newValue) => {
    setSearch(newValue);
    setDisplay(false);
  };

  const displayList = event => {
    if (event.altKey && event.shiftKey) {
      event.target.classList.add(styles.loading);
      // Lazy load duty values
      let response = []
      $.ajax({
        data: {
          'user_token': user_token,
          'token': token,
          'language_id': parseInt(data.tree.$div[0].id.replace(/\D+/ig, '')),
          'attribute_id': parseInt(data.node.key.replace(/\D+/ig, ''))
        },
        url: route + 'getValuesList',
        dataType: 'json',
        success: function (json) {
          response = json.map(item => {
            return {
              label: item.text,
              value: item.text
            }
          })
          setOptions(response);
          event.target.classList.remove(styles.loading);
          setDisplay(true)
          // Защита от срабатывания enter (submit) на элементах списка
          //event.target.blur() 
          console.log('duty is loaded');
        }
      });
    } else {
      setDisplay(false)
    }
  }

  /* Если нужно установить фокус на условно отрендеренных компонентах. Фокус устанавливается только на видимых. */
  /* useEffect(() => {
    if (display) {
      //console.log(wrapperRef.current.children.length); 
      console.log('dispaly',wrapperRef.current.lastElementChild.firstElementChild, display)
      wrapperRef.current.lastElementChild.firstElementChild?.focus()
      //inputReference.current?.focus();
    }
}, [display]); */

  return (
    <div ref={wrapperRef}>
      <input
        type="text" className="form-control"
        id={name}
        name={name}
        onClick={event => displayList(event)}
        placeholder={placeholder}
        value={search}
        onChange={event => handleOnChange(event)}
        ref={ref}
      />
      {display && (
        <ul className={styles.autoContainer}>
          {options
            //.filter(option => (filter ? option.value.toLowerCase().indexOf(search.toLowerCase()) > -1 : true))
            .map((option, i) => {
              return (
                <li
                  onClick={(e) => handleSelect(option.value)}
                  onKeyDown={(e) => handleSelect(option.value)}
                  key={i}
                  tabIndex={0}
                >
                  <span>{option.value}</span>
                </li>
              );
            })}
        </ul>
      )}
    </div>
  );
});

export default Autocomplete;
